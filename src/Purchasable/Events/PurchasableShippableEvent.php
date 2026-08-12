<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use craft\commerce\elements\Order;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;

class PurchasableShippableEvent
{
    public function __construct(
        public PurchasableInterface $purchasable,
        public bool $isShippable,
        public ?Order $order = null,
        public ?User $currentUser = null,
    ) {}
}
