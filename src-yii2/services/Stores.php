<?php

namespace craft\commerce\services;

use craft\commerce\models\SiteStore;
use craft\commerce\models\Store;
use craft\events\ConfigEvent;
use craft\events\SiteEvent;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Stores::class)` instead.
 */
class Stores extends Component
{
    public const EVENT_BEFORE_DELETE_STORE = \CraftCms\Commerce\Services\Stores::EVENT_BEFORE_DELETE_STORE;

    public const EVENT_AFTER_DELETE_STORE = \CraftCms\Commerce\Services\Stores::EVENT_AFTER_DELETE_STORE;

    public const EVENT_BEFORE_APPLY_STORE_DELETE = \CraftCms\Commerce\Services\Stores::EVENT_BEFORE_APPLY_STORE_DELETE;

    public const EVENT_BEFORE_SAVE_STORE = \CraftCms\Commerce\Services\Stores::EVENT_BEFORE_SAVE_STORE;

    public const EVENT_AFTER_SAVE_STORE = \CraftCms\Commerce\Services\Stores::EVENT_AFTER_SAVE_STORE;

    public const CONFIG_STORES_KEY = \CraftCms\Commerce\Services\Stores::CONFIG_STORES_KEY;

    public const CONFIG_SITESTORES_KEY = \CraftCms\Commerce\Services\Stores::CONFIG_SITESTORES_KEY;

    public function getCurrentStore(): Store
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getCurrentStore();
    }

    /**
     * @return Collection<int, Store>
     */
    public function getAllStores(): Collection
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getAllStores();
    }

    public function getStoreById(int $id): ?Store
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getStoreById($id);
    }

    public function getStoreByUid(string $uid): ?Store
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getStoreByUid($uid);
    }

    public function getStoreBySiteId(int $siteId): ?Store
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getStoreBySiteId($siteId);
    }

    public function getStoreByHandle(string $handle): ?Store
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getStoreByHandle($handle);
    }

    /**
     * @return Collection<int, Store>
     */
    public function getStoresByUserId(int $userId): Collection
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getStoresByUserId($userId);
    }

    public function saveStore(Store $store, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->saveStore($store, $runValidation);
    }

    public function deleteStoreById(int $storeId): bool
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->deleteStoreById($storeId);
    }

    public function deleteStore(Store $store): bool
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->deleteStore($store);
    }

    /**
     * @throws Throwable
     */
    public function handleChangedStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->handleChangedStore($event);
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->handleDeletedStore($event);
    }

    public function refreshStores(): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->refreshStores();
    }

    public function getPrimaryStore(): ?Store
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getPrimaryStore();
    }

    /**
     * @param int[] $ids
     */
    public function reorderStores(array $ids): bool
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->reorderStores($ids);
    }

    /**
     * @return Collection<int, \craft\models\Site>
     */
    public function getAllSitesForStore(Store $store): Collection
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getAllSitesForStore($store);
    }

    /**
     * @return Collection<int, SiteStore>
     */
    public function getAllSiteStores(): Collection
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getAllSiteStores();
    }

    public function getSiteIdsAvailableForAssignmentToNewStores(): array
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->getSiteIdsAvailableForAssignmentToNewStores();
    }

    /**
     * @throws Throwable
     */
    public function saveSiteStore(SiteStore $siteStore, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\Stores::class)->saveSiteStore($siteStore, $runValidation);
    }

    /**
     * @throws Throwable
     */
    public function handleChangedSiteStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->handleChangedSiteStore($event);
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedSiteStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->handleDeletedSiteStore($event);
    }

    /**
     * @throws Throwable
     */
    public function afterSaveCraftSiteHandler(SiteEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->afterSaveCraftSiteHandler($event);
    }

    /**
     * @throws Throwable
     */
    public function afterDeleteCraftSiteHandler(SiteEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Stores::class)->afterDeleteCraftSiteHandler($event);
    }
}
