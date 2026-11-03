<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\OrderStatusEmailsResolving} instead.
 */
class OrderStatusEmailsEvent extends CancelableEvent
{
    /**
     * @var OrderHistory The order history
     */
    public OrderHistory $orderHistory;

    /**
     * @var Order The order
     */
    public Order $order;

    /**
     * @var array The emails to send
     */
    public array $emails;
}
