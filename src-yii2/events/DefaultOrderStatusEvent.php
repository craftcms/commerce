<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\DefaultOrderStatusResolving} instead.
 */
class DefaultOrderStatusEvent extends Event
{
    /**
     * @var OrderStatus|null The default order status based on the order
     */
    public ?OrderStatus $orderStatus = null;

    /**
     * @var Order The order used to determine the order status.
     */
    public Order $order;
}
