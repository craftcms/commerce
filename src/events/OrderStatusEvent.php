<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use craft\events\CancelableEvent;

class OrderStatusEvent extends CancelableEvent
{
    /**
     * @var OrderHistory The order history
     */
    public $orderHistory;

    /**
     * @var Order The order
     */
    public $order;
}
