<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * ModifyCartInfoEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class ModifyCartInfoEvent extends Event
{
    /**
     * @var array The cart info that is allowed to be modified
     */
    public $cartInfo = [];


    /**
     * The cart object that can be used to modify the cart info.
     * Do not mutate this object.
     *
     * @var Order|null
     * @since 3.1.11
     */
    public $cart;
}
