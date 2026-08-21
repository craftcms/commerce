<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Models\OrderHistory;

class OrderStatusEvent
{
    public function __construct(
        public OrderHistory $orderHistory,
        public Order $order,
    ) {
    }
}
