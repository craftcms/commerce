<?php

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * Class OrderEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
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
