<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\elements\User;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Purchasables::class)` instead.
 */
class Purchasables extends Component
{
    public const EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED = \CraftCms\Commerce\Services\Purchasables::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED;

    public const EVENT_PURCHASABLE_AVAILABLE = \CraftCms\Commerce\Services\Purchasables::EVENT_PURCHASABLE_AVAILABLE;

    public const EVENT_PURCHASABLE_SHIPPABLE = \CraftCms\Commerce\Services\Purchasables::EVENT_PURCHASABLE_SHIPPABLE;

    public const EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES = \CraftCms\Commerce\Services\Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES;

    /**
     * @throws Throwable
     */
    public function isPurchasableOutOfStockPurchasingAllowed(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        return app(\CraftCms\Commerce\Services\Purchasables::class)->isPurchasableOutOfStockPurchasingAllowed($purchasable, $order, $currentUser);
    }

    public function isPurchasableAvailable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        return app(\CraftCms\Commerce\Services\Purchasables::class)->isPurchasableAvailable($purchasable, $order, $currentUser);
    }

    public function isPurchasableShippable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        return app(\CraftCms\Commerce\Services\Purchasables::class)->isPurchasableShippable($purchasable, $order, $currentUser);
    }

    public function updateStoreStockCache(PurchasableInterface $purchasable, bool $allSites = false): void
    {
        app(\CraftCms\Commerce\Services\Purchasables::class)->updateStoreStockCache($purchasable, $allSites);
    }

    /**
     * @throws Throwable
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        return app(\CraftCms\Commerce\Services\Purchasables::class)->deletePurchasableById($purchasableId);
    }

    public function getPurchasableById(int $purchasableId, ?int $siteId = null, int|false|null $forCustomer = null): ?PurchasableInterface
    {
        return app(\CraftCms\Commerce\Services\Purchasables::class)->getPurchasableById($purchasableId, $siteId, $forCustomer);
    }

    /**
     * @return string[]
     */
    public function getAllPurchasableElementTypes(): array
    {
        return app(\CraftCms\Commerce\Services\Purchasables::class)->getAllPurchasableElementTypes();
    }
}
