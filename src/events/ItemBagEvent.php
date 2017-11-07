<?php

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * Class ItemBagEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
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
