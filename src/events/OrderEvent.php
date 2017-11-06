<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class OrderEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Order The order
     */
    public $order;

    /**
     * @var bool If this is a new order
     */
    public $isNew;
}
