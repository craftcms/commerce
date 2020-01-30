<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\Email;
use craft\commerce\models\OrderHistory;
use craft\events\CancelableEvent;
use craft\mail\Message;

/**
 * Class MailEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class MailEvent extends CancelableEvent
{
    /**
     * @var Message Craft email object
     */
    public $craftEmail;

    /**
     * @var Email Commerce email object
     */
    public $commerceEmail;

    /**
     * @var Order Commerce order
     */
    public $order;

    /**
     * @var OrderHistory The order history
     */
    public $orderHistory;

    /**
     * @var array Order data at the time the email sends.
     */
    public $orderData;
}
