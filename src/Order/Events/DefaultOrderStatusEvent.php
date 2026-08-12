<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Models\OrderStatus;

class DefaultOrderStatusEvent
{
    public function __construct(
        public ?OrderStatus $orderStatus,
        public Order $order,
    ) {}
}
