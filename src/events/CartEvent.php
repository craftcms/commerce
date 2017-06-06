<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\events\CancelableEvent;

class CartEvent extends CancelableEvent
{
    /**
     * @var LineItem The line item model.
     */
    public $lineItem;

    /**
     * @var Order The order element
     */
    public $order;
}
