<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;

/**
 * Dispatched when resolving whether a purchasable is available to an order and user. Listeners may change `$isAvailable`.
 */
class PurchasableAvailabilityResolving
{
    public function __construct(
        public PurchasableInterface $purchasable,
        public bool $isAvailable,
        public ?Order $order = null,
        public ?User $currentUser = null,
    ) {
    }
}
