<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool isPurchasableOutOfStockPurchasingAllowed(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, \CraftCms\Commerce\Order\Elements\Order|null $order = null, \CraftCms\Cms\User\Elements\User|null $currentUser = null)
 * @method static bool isPurchasableAvailable(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, \CraftCms\Commerce\Order\Elements\Order|null $order = null, \CraftCms\Cms\User\Elements\User|null $currentUser = null)
 * @method static bool isPurchasableShippable(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, \CraftCms\Commerce\Order\Elements\Order|null $order = null, \CraftCms\Cms\User\Elements\User|null $currentUser = null)
 * @method static void updateStoreStockCache(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, bool $allSites = false)
 * @method static bool deletePurchasableById(int $purchasableId)
 * @method static void forgetCachedPurchasable(int $purchasableId)
 * @method static \CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface|null getPurchasableById(int $purchasableId, int|null $siteId = null, int|false|null $forCustomer = null)
 * @method static string[] getAllPurchasableElementTypes()
 *
 * @see \CraftCms\Commerce\Purchasable\Purchasables
 */
class Purchasables extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Purchasable\Purchasables::class;
    }
}
