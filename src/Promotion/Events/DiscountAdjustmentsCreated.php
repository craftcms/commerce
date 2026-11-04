<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Data\Discount;

/**
 * Dispatched after a discount matches an order and creates its adjustments. Listeners may change `$adjustments`, or set `$isValid` to `false` to discard them.
 */
class DiscountAdjustmentsCreated
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public Discount $discount,
        /** @var OrderAdjustment[] */
        public array $adjustments,
    ) {
    }
}
