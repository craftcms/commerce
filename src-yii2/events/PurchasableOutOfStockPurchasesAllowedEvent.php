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
 * Class PurchasableOutOfStockPurchasesAllowedEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class PurchasableOutOfStockPurchasesAllowedEvent extends Event
{
    /**
     * @var Order|null The order element.
     */
    public ?Order $order = null;

    /**
     * @var PurchasableInterface The purchasable element.
     */
    public PurchasableInterface $purchasable;

    /**
     * @var User|null The user performing the check.
     */
    public ?User $currentUser = null;

    /**
     * @var bool Is this purchasable available to be purchased when out of stock
     */
    public bool $outOfStockPurchasesAllowed = false;
}
