<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Events;

use craft\mail\Message;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;

class MailEvent
{
    use ValidatableEvent;

    public function __construct(
        public Message $craftEmail,
        public Email $commerceEmail,
        public Order $order,
        public ?OrderHistory $orderHistory = null,
        public ?array $orderData = null,
    ) {
    }
}
