<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * Dispatched before the emails for an order status change are sent. Listeners may change `$emails`, or set `$isValid` to `false` to send none.
 */
class OrderStatusEmailsResolving
{
    use ValidatableEvent;

    public function __construct(
        public OrderHistory $orderHistory,
        public Order $order,
        /** @var Email[] */
        public array $emails,
    ) {
    }
}
