<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use yii\base\Event;

/**
 * Class OrderStatusEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatusEvent extends Event
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
