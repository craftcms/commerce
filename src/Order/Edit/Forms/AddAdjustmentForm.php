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
use CraftCms\Commerce\Order\Edit\Operations\AddAdjustment;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class AddAdjustmentForm extends OrderOperationForm
{
    public static function type(): string
    {
        return AddAdjustment::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Add Adjustment', category: 'commerce');
    }

    public function submitLabel(Order $order): string
    {
        return t('Add', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        $lineItems = array_map(
            fn($lineItem) => ['label' => $lineItem->getDescription(), 'value' => (string)$lineItem->uid],
            array_values($order->getLineItems()),
        );

        return Ui::make([
            Field::make(t('Type', category: 'commerce'), Choice::make('adjustmentType')->options($this->typeOptions())->withoutPlaceholder())->required(),
            Field::make(t('Name', category: 'commerce'), Text::make('name'))->required(),
            Field::make(t('Description', category: 'commerce'), Textarea::make('description')->rows(2)),
            Field::make(t('Amount', category: 'commerce'), Money::make('amount')->currency($order->currency)->size(12))
                ->instructions(t('A discount must be a negative amount.', category: 'commerce'))
                ->required(),
            Field::make(t('Included in price', category: 'commerce'), Lightswitch::make('included')),
            Field::make(t('Applies to', category: 'commerce'), Choice::make('lineItemUid')->options([
                ['label' => t('The whole order', category: 'commerce'), 'value' => ''],
                ...$lineItems,
            ])->withoutPlaceholder()),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return [
            'adjustmentType' => AddAdjustment::TYPES[0],
            'name' => '',
            'description' => '',
            'amount' => null,
            'included' => false,
            'lineItemUid' => (string)($params['lineItemUid'] ?? ''),
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        return [
            'adjustmentType' => $input['adjustmentType'] ?? null,
            'name' => trim((string)($input['name'] ?? '')),
            'description' => $this->nullableId($input['description'] ?? null),
            'amount' => $this->money($input['amount'] ?? null),
            'included' => (bool)($input['included'] ?? false),
            'lineItemUid' => $this->nullableId($input['lineItemUid'] ?? null),
            'uid' => (string)Str::uuid(),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn(string $type) => ['label' => t(Str::title($type), category: 'commerce'), 'value' => $type],
            AddAdjustment::TYPES,
        );
    }
}
