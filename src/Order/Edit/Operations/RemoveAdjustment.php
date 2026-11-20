<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Removes an adjustment from a completed order, either a saved one by its ID or one added earlier in
 * the same edit by its `uid`.
 *
 * @since 6.0.0
 */
final readonly class RemoveAdjustment extends OrderOperation
{
    public function __construct(
        public ?int $adjustmentId,
        public ?string $adjustmentUid = null,
    ) {
    }

    public static function type(): string
    {
        return 'removeAdjustment';
    }

    public function label(): string
    {
        return t('Remove Adjustment', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'adjustmentId' => ['required_without:adjustmentUid', 'nullable', 'integer'],
            'adjustmentUid' => ['required_without:adjustmentId', 'nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['adjustmentId']) ? (int)$data['adjustmentId'] : null,
            $data['adjustmentUid'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'adjustmentId' => $this->adjustmentId,
            'adjustmentUid' => $this->adjustmentUid,
        ], fn($value) => $value !== null);
    }

    public function summary(Order $before): ?string
    {
        try {
            $adjustment = UpdateAdjustment::find($before, $this->adjustmentId, $this->adjustmentUid);
        } catch (OperationNotApplicableException) {
            return null;
        }

        return t('{name} · {amount}', ['name' => $adjustment->name, 'amount' => $this->currency($before, (float)$adjustment->amount)], category: 'commerce');
    }

    /**
     * Removing an adjustment added earlier in the edit leaves nothing to do, so both go.
     */
    public function cancels(OrderOperation $earlier): bool
    {
        if ($this->adjustmentUid === null) {
            return false;
        }

        return ($earlier instanceof AddAdjustment && $earlier->uid === $this->adjustmentUid)
            || ($earlier instanceof UpdateAdjustment && $earlier->adjustmentUid === $this->adjustmentUid);
    }

    /**
     * Removing a saved adjustment makes earlier changes to it pointless.
     */
    public function absorb(OrderOperation $earlier): ?static
    {
        if ($earlier instanceof UpdateAdjustment && $this->adjustmentId !== null && $earlier->adjustmentId === $this->adjustmentId) {
            return $this;
        }

        return null;
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        $removed = UpdateAdjustment::find($order, $this->adjustmentId, $this->adjustmentUid);

        $order->setAdjustments(array_values(array_filter(
            $order->getAdjustments() ?? [],
            fn($adjustment) => $adjustment !== $removed,
        )));
    }
}
