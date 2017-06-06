<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\events\CancelableEvent;

class OrderEvent extends CancelableEvent
{
    /**
     * @var Order The address model
     */
    public $order;

    /**
     * @var bool If this is a new order
     */
    public $isNew;
}
