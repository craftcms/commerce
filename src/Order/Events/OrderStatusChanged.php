<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * Dispatched after an order's status changes.
 */
class OrderStatusChanged
{
    public function __construct(
        public OrderHistory $orderHistory,
        public Order $order,
    ) {
    }
}
