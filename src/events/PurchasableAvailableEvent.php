<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\elements\User;
use yii\base\Event;

/**
 * Class PurchasableAvailableEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.1
 */
class PurchasableAvailableEvent extends Event
{
    /**
     * @var Order|null The order element.
     */
    public $order;

    /**
     * @var PurchasableInterface The purchasable element.
     */
    public $purchasable;

    /**
     * @var User|null The user performing the check.
     */
    public $currentUser;

    /**
     * @var bool Is this purchasable available to the order and current user. Default is: $event->purchasable->getIsAvailable()
     */
    public $isAvailable;
}
