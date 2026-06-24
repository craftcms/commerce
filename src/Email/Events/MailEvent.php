<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\Email;
use craft\commerce\models\OrderHistory;
use craft\mail\Message;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class MailEvent
{
    use ValidatableEvent;

    public Message $craftEmail;
    public Email $commerceEmail;
    public Order $order;
    public ?OrderHistory $orderHistory = null;
    public ?array $orderData = null;
}
