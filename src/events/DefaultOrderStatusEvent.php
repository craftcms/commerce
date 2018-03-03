<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderStatus;
use yii\base\Event;

/**
 * Class DefaultOrderStatusEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DefaultOrderStatusEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var OrderStatus The default order status based on the order
     */
    public $orderStatus;

    /**
     * @var Order The order used to determine the order status.
     */
    public $order;
}
