<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;
use yii\base\Event;

class OrderStatusEvent extends Event
{
    public function __construct(
        public OrderHistory $orderHistory,
        public Order $order,
    ) {
    }
}
