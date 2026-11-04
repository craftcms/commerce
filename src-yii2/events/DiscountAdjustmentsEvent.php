<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Data\Discount;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsCreated} instead.
 */
class DiscountAdjustmentsEvent extends CancelableEvent
{
    /**
     * @var Order The order
     */
    public Order $order;

    /**
     * @var Discount The discount
     */
    public Discount $discount;

    /**
     * @var array The adjustments created by the discount
     */
    public array $adjustments;
}
