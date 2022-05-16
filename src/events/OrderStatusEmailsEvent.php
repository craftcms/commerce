<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use craft\events\CancelableEvent;

/**
 * Class OrderStatusEmailsEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class OrderStatusEmailsEvent extends CancelableEvent
{
    /**
     * @var OrderHistory The order history
     */
    public OrderHistory $orderHistory;

    /**
     * @var Order The order
     */
    public Order $order;

    /**
     * @var array The emails to send
     */
    public array $emails;
}
