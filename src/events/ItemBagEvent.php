<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class ItemBagEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Order The order
     */
    public $order;

    /**
     * @var mixed The item bag
     */
    public $items;
}
