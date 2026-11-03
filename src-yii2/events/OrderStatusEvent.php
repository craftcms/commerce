<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\OrderStatusChanged} instead.
 */
class OrderStatusEvent extends Event
{
    /**
     * @var OrderHistory The order history
     */
    public OrderHistory $orderHistory;

    /**
     * @var Order The order
     */
    public Order $order;
}
