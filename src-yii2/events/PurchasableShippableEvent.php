<?php

namespace craft\commerce\events;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Purchasable\Events\PurchasableShippabilityResolving} instead.
 */
class PurchasableShippableEvent extends Event
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
     * @var bool Whether the purchasable is shippable within the order.
     */
    public bool $isShippable;
}
