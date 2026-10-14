<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * Fully recalculates a completed order at this point in the edit: line item prices are refreshed
 * from their purchasables and every adjustment is regenerated, replacing any manual ones.
 *
 * Carts are recalculated after every edit anyway, so this does nothing to them.
 *
 * @since 6.0.0
 */
final readonly class Recalculate extends OrderOperation
{
    public static function type(): string
    {
        return 'recalculate';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }

    public function apply(Order $order): void
    {
        if (!$order->isCompleted) {
            return;
        }

        $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        $order->recalculate();
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
    }
}
