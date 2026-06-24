<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderStatus;

class DefaultOrderStatusEvent
{
    public OrderStatus $orderStatus;
    public Order $order;
}
