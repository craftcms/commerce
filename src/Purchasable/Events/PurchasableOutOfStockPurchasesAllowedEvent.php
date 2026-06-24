<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use craft\commerce\elements\Order;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;

class PurchasableOutOfStockPurchasesAllowedEvent
{
    public ?Order $order = null;
    public PurchasableInterface $purchasable;
    public ?User $currentUser = null;
    public bool $outOfStockPurchasesAllowed = false;
}
