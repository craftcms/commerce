<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class OrderStatusEmailsEvent
{
    use ValidatableEvent;

    public OrderHistory $orderHistory;
    public Order $order;
    public array $emails;
}
