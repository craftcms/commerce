<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * RegisterAvailableShippingMethodsEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class RegisterAvailableShippingMethodsEvent extends Event
{
    /**
     * @var Order The order the shipping method should be available for
     */
    public $order;

    /**
     * @var ShippingMethodInterface[] The shipping methods available to the order.
     */
    public $shippingMethods = [];
}
