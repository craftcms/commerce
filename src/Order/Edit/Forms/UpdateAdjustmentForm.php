<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Money;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\Operations\AddAdjustment;
use CraftCms\Commerce\Order\Edit\Operations\UpdateAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Validation\ValidationException;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class UpdateAdjustmentForm extends OrderOperationForm
{
    public static function type(): string
    {
        return UpdateAdjustment::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Edit {name}', ['name' => $this->adjustment($order, $params)->name], category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(t('Type', category: 'commerce'), Choice::make(['changes', 'adjustmentType'])->options(array_map(
                fn(string $type) => ['label' => t(Str::title($type), category: 'commerce'), 'value' => $type],
                AddAdjustment::TYPES,
            ))->withoutPlaceholder())->required(),
            Field::make(t('Name', category: 'commerce'), Text::make(['changes', 'name']))->required(),
            Field::make(t('Description', category: 'commerce'), Textarea::make(['changes', 'description'])->rows(2)),
            Field::make(t('Amount', category: 'commerce'), Money::make(['changes', 'amount'])->currency($order->currency)->size(12))
                ->instructions(t('A discount must be a negative amount.', category: 'commerce'))
                ->required(),
            Field::make(t('Included in price', category: 'commerce'), Lightswitch::make(['changes', 'included'])),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        $adjustment = $this->adjustment($order, $params);

        return ['changes' => [
            'adjustmentType' => $adjustment->type,
            'name' => $adjustment->name,
            'description' => (string)$adjustment->description,
            'amount' => $adjustment->amount,
            'included' => (bool)$adjustment->included,
        ]];
    }

    /**
     * Only what the user changed is submitted, so an adjustment the screen couldn't have created, such
     * as a positive discount, can be renamed without being rejected for its amount.
     */
    public function operationData(Order $order, array $input): array
    {
        $adjustment = $this->adjustment($order, $input);
        $submitted = (array)($input['changes'] ?? []);
        $changes = [];

        $type = $submitted['adjustmentType'] ?? $adjustment->type;

        if ($type !== $adjustment->type) {
            $changes['adjustmentType'] = $type;
        }

        $name = trim((string)($submitted['name'] ?? ''));

        if ($name !== $adjustment->name) {
            $changes['name'] = $name;
        }

        $description = $this->nullableId($submitted['description'] ?? null);

        if ($description !== ($adjustment->description === '' ? null : $adjustment->description)) {
            $changes['description'] = $description;
        }

        $amount = $this->money($submitted['amount'] ?? null);

        if ($amount === null || (float)$amount !== (float)$adjustment->amount) {
            $changes['amount'] = $amount;
        }

        $included = (bool)($submitted['included'] ?? false);

        if ($included !== (bool)$adjustment->included) {
            $changes['included'] = $included;
        }

        if (
            (isset($changes['adjustmentType']) || isset($changes['amount']))
            && $type === Discount::ADJUSTMENT_TYPE
            && (float)($changes['amount'] ?? $adjustment->amount) >= 0
        ) {
            throw ValidationException::withMessages([
                'changes.amount' => t('A discount must be a negative amount.', category: 'commerce'),
            ]);
        }

        return [
            ...($adjustment->id !== null ? ['adjustmentId' => $adjustment->id] : ['adjustmentUid' => $adjustment->uid]),
            'changes' => $changes,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function adjustment(Order $order, array $params): OrderAdjustment
    {
        $id = ($params['adjustmentId'] ?? '') !== '' ? (int)$params['adjustmentId'] : null;
        $uid = ($params['adjustmentUid'] ?? '') !== '' ? (string)$params['adjustmentUid'] : null;

        try {
            return UpdateAdjustment::find($order, $id, $uid);
        } catch (OperationNotApplicableException $exception) {
            abort(404, $exception->getMessage());
        }
    }
}
