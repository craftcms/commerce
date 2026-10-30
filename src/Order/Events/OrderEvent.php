<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;
use yii\base\Event;

class OrderEvent extends Event
{
    public function __construct(
        public Order $order,
    ) {
    }
}
