<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Removes a saved adjustment from a completed order.
 *
 * @since 6.0.0
 */
final readonly class RemoveAdjustment extends OrderOperation
{
    public function __construct(
        public int $adjustmentId,
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
            'adjustmentId' => ['required', 'integer'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self((int)$data['adjustmentId']);
    }

    public function toArray(): array
    {
        return ['adjustmentId' => $this->adjustmentId];
    }

    public function summary(Order $before): ?string
    {
        try {
            $adjustment = UpdateAdjustment::find($before, $this->adjustmentId);
        } catch (OperationNotApplicableException) {
            return null;
        }

        return t('{name} · {amount}', ['name' => $adjustment->name, 'amount' => $this->currency($before, (float)$adjustment->amount)], category: 'commerce');
    }

    public function apply(Order $order): void
    {
        $this->requireCompletedOrder($order);

        $removed = UpdateAdjustment::find($order, $this->adjustmentId);

        $order->setAdjustments(array_values(array_filter(
            $order->getAdjustments() ?? [],
            fn($adjustment) => $adjustment !== $removed,
        )));
    }
}
