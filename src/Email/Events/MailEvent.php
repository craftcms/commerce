<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Mailables\OrderEmail;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;

abstract class MailEvent
{
    use ValidatableEvent;

    public function __construct(
        public OrderEmail $mailable,
        public Email $commerceEmail,
        public Order $order,
        public ?OrderHistory $orderHistory = null,
        public ?array $orderData = null,
    ) {
    }
}
