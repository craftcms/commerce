<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

use function CraftCms\Cms\t;

/**
 * Changes an adjustment on a completed order, either a saved one by its ID or one added earlier in
 * the same edit by its `uid`. Only the keys present in `changes` are applied.
 *
 * @since 6.0.0
 */
final readonly class UpdateAdjustment extends OrderOperation
{
    /**
     * @param array{
     *     adjustmentType?: string,
     *     name?: string,
     *     description?: string|null,
     *     amount?: float,
     *     included?: bool,
     * } $changes
     */
    public function __construct(
        public ?int $adjustmentId,
        public array $changes,
        public ?string $adjustmentUid = null,
    ) {
    }

    public static function type(): string
    {
        return 'updateAdjustment';
    }

    public function label(): string
    {
        return t('Update Adjustment', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'adjustmentId' => ['required_without:adjustmentUid', 'nullable', 'integer'],
            'adjustmentUid' => ['required_without:adjustmentId', 'nullable', 'string'],
            'changes' => ['present', 'array'],
            'changes.adjustmentType' => ['in:' . implode(',', AddAdjustment::TYPES)],
            'changes.name' => ['string'],
            'changes.description' => ['nullable', 'string'],
            'changes.amount' => ['numeric', Rule::when(
                fn(Fluent $input) => ($input->get('changes')['adjustmentType'] ?? null) === Discount::ADJUSTMENT_TYPE,
                [AddAdjustment::negativeDiscount()],
            )],
            'changes.included' => ['boolean'],
        ];
    }

    public static function fromArray(array $data): static
    {
        $changes = array_intersect_key($data['changes'], array_flip(['adjustmentType', 'name', 'description', 'amount', 'included']));

        if (isset($changes['amount'])) {
            $changes['amount'] = (float)$changes['amount'];
        }

        if (isset($changes['included'])) {
            $changes['included'] = (bool)$changes['included'];
        }

        return new self(
            isset($data['adjustmentId']) ? (int)$data['adjustmentId'] : null,
            $changes,
            $data['adjustmentUid'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'adjustmentId' => $this->adjustmentId,
            'adjustmentUid' => $this->adjustmentUid,
        ], fn($value) => $value !== null) + ['changes' => $this->changes];
    }

    public function summary(Order $before): string
    {
        $adjustment = self::find($before, $this->adjustmentId, $this->adjustmentUid);

        if (isset($this->changes['amount'])) {
            return t('{name}: {change}', [
                'name' => $this->changes['name'] ?? $adjustment->name,
                'change' => $this->change($this->currency($before, (float)$adjustment->amount), $this->currency($before, (float)$this->changes['amount'])),
            ], category: 'commerce');
        }

        return $this->changes['name'] ?? $adjustment->name;
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        if (!$earlier instanceof self || !$this->sameAdjustment($earlier->adjustmentId, $earlier->adjustmentUid)) {
            return null;
        }

        return new self($this->adjustmentId, [...$earlier->changes, ...$this->changes], $this->adjustmentUid);
    }

    public function isUnchangedFor(Order $order): bool
    {
        return $this->changes === [];
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        $adjustment = self::find($order, $this->adjustmentId, $this->adjustmentUid);

        if (isset($this->changes['adjustmentType'])) {
            $adjustment->type = $this->changes['adjustmentType'];
        }

        if (isset($this->changes['name'])) {
            $adjustment->name = $this->changes['name'];
        }

        if (array_key_exists('description', $this->changes)) {
            $adjustment->description = $this->changes['description'];
        }

        if (isset($this->changes['amount'])) {
            $adjustment->amount = $this->changes['amount'];
        }

        if (isset($this->changes['included'])) {
            $adjustment->included = $this->changes['included'];
        }

        if (
            (isset($this->changes['adjustmentType']) || isset($this->changes['amount']))
            && $adjustment->type === Discount::ADJUSTMENT_TYPE
            && $adjustment->amount >= 0
        ) {
            throw new OperationNotApplicableException(t('A discount must be a negative amount.', category: 'commerce'));
        }
    }

    public static function find(Order $order, ?int $adjustmentId, ?string $adjustmentUid = null): OrderAdjustment
    {
        foreach ($order->getAdjustments() ?? [] as $adjustment) {
            if (($adjustmentId !== null && $adjustment->id === $adjustmentId) || ($adjustmentUid !== null && $adjustment->uid === $adjustmentUid)) {
                return $adjustment;
            }
        }

        throw new OperationNotApplicableException(t('The adjustment no longer exists.', category: 'commerce'));
    }

    private function sameAdjustment(?int $adjustmentId, ?string $adjustmentUid): bool
    {
        return ($adjustmentId !== null && $adjustmentId === $this->adjustmentId)
            || ($adjustmentUid !== null && $adjustmentUid === $this->adjustmentUid);
    }
}
