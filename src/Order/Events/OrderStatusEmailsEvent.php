<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;
use yii\base\Event;

class OrderStatusEmailsEvent extends Event
{
    use ValidatableEvent;

    public function __construct(
        public OrderHistory $orderHistory,
        public Order $order,
        public array $emails,
    ) {
    }
}
