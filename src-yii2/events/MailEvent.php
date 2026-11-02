<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use craft\mail\Message;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * @deprecated 6.0.0 No longer triggered. Order emails are sent as {@see \CraftCms\Commerce\Email\Mailables\OrderEmail} mailables; use {@see \CraftCms\Commerce\Email\Events\MailSending} and {@see \CraftCms\Commerce\Email\Events\MailSent} instead.
 */
class MailEvent extends CancelableEvent
{
    public Message $craftEmail;

    public Email $commerceEmail;

    public Order $order;

    public ?OrderHistory $orderHistory = null;

    public ?array $orderData = null;
}
