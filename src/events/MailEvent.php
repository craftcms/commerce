<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\Email;
use craft\commerce\models\OrderHistory;
use craft\events\CancelableEvent;
use craft\mail\Message;

class MailEvent extends CancelableEvent
{
    // Properties
    // =============================================================================

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
}
