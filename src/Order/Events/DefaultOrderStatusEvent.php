<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;

class DefaultOrderStatusEvent
{
    public function __construct(
        public ?OrderStatus $orderStatus,
        public Order $order,
    ) {
    }
}
