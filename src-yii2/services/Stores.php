<?php

namespace craft\commerce\services;

use craft\commerce\Plugin;
use CraftCms\Commerce\Store\Events\StoreDeleteApplying;
use CraftCms\Commerce\Store\Events\StoreDeleted;
use CraftCms\Commerce\Store\Events\StoreDeleting;
use CraftCms\Commerce\Store\Events\StoreSaved;
use CraftCms\Commerce\Store\Events\StoreSaving;
use Illuminate\Support\Facades\Event;

use craft\events\ConfigEvent;
use CraftCms\Commerce\Store\Data\SiteStore;
use CraftCms\Commerce\Store\Data\Store;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Store\Stores::class)` instead.
 *
 * The `Store`/`SiteStore` type-hints below intentionally use the new `CraftCms\Commerce\Store\Models\*`
 * classes directly rather than the `craft\commerce\models\*` aliases: PHP's return-type check can
 * lose a race against `class_alias()`'s own autoload on the very first call to an aliased type in a
 * process (the alias file autoloads *during* the check, but the check doesn't see it as satisfied
 * until the next call) — using the real class sidesteps it. Both names refer to the identical class,
 * so this is a no-op for callers still type-hinting against the legacy alias.
 */
class Stores extends Component
{
    public const EVENT_BEFORE_DELETE_STORE = \CraftCms\Commerce\Store\Stores::EVENT_BEFORE_DELETE_STORE;

    public const EVENT_AFTER_DELETE_STORE = \CraftCms\Commerce\Store\Stores::EVENT_AFTER_DELETE_STORE;

    public const EVENT_BEFORE_APPLY_STORE_DELETE = \CraftCms\Commerce\Store\Stores::EVENT_BEFORE_APPLY_STORE_DELETE;

    public const EVENT_BEFORE_SAVE_STORE = \CraftCms\Commerce\Store\Stores::EVENT_BEFORE_SAVE_STORE;

    public const EVENT_AFTER_SAVE_STORE = \CraftCms\Commerce\Store\Stores::EVENT_AFTER_SAVE_STORE;

    public const CONFIG_STORES_KEY = \CraftCms\Commerce\Store\Stores::CONFIG_STORES_KEY;

    public const CONFIG_SITESTORES_KEY = \CraftCms\Commerce\Store\Stores::CONFIG_SITESTORES_KEY;

    public function getCurrentStore(): Store
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getCurrentStore();
    }

    /**
     * @return Collection<int, Store>
     */
    public function getAllStores(): Collection
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getAllStores();
    }

    public function getStoreById(int $id): ?Store
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getStoreById($id);
    }

    public function getStoreByUid(string $uid): ?Store
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getStoreByUid($uid);
    }

    public function getStoreBySiteId(int $siteId): ?Store
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getStoreBySiteId($siteId);
    }

    public function getStoreByHandle(string $handle): ?Store
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getStoreByHandle($handle);
    }

    /**
     * @return Collection<int, Store>
     */
    public function getStoresByUserId(int $userId): Collection
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getStoresByUserId($userId);
    }

    public function saveStore(Store $store, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->saveStore($store, $runValidation);
    }

    public function deleteStoreById(int $storeId): bool
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->deleteStoreById($storeId);
    }

    public function deleteStore(Store $store): bool
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->deleteStore($store);
    }

    /**
     * @throws Throwable
     */
    public function handleChangedStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Store\Stores::class)->handleChangedStore(new \CraftCms\Cms\ProjectConfig\Events\ItemUpdated($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Store\Stores::class)->handleDeletedStore(new \CraftCms\Cms\ProjectConfig\Events\ItemRemoved($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }

    public function refreshStores(): void
    {
        app(\CraftCms\Commerce\Store\Stores::class)->refreshStores();
    }

    public function getPrimaryStore(): ?Store
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getPrimaryStore();
    }

    /**
     * @param int[] $ids
     */
    public function reorderStores(array $ids): bool
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->reorderStores($ids);
    }

    /**
     * @return Collection<int, \craft\models\Site>
     */
    public function getAllSitesForStore(Store $store): Collection
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getAllSitesForStore($store);
    }

    /**
     * @return Collection<int, SiteStore>
     */
    public function getAllSiteStores(): Collection
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getAllSiteStores();
    }

    public function getSiteIdsAvailableForAssignmentToNewStores(): array
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->getSiteIdsAvailableForAssignmentToNewStores();
    }

    /**
     * @throws Throwable
     */
    public function saveSiteStore(SiteStore $siteStore, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Store\Stores::class)->saveSiteStore($siteStore, $runValidation);
    }

    /**
     * @throws Throwable
     */
    public function handleChangedSiteStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Store\Stores::class)->handleChangedSiteStore(new \CraftCms\Cms\ProjectConfig\Events\ItemUpdated($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedSiteStore(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Store\Stores::class)->handleDeletedSiteStore(new \CraftCms\Cms\ProjectConfig\Events\ItemRemoved($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }


    public static function registerEvents(): void
    {
        Event::listen(StoreSaving::class, static function(StoreSaving $event) {
            $legacy = Plugin::getInstance()->getStores();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_SAVE_STORE)) {
                $legacy->trigger(self::EVENT_BEFORE_SAVE_STORE, $event);
            }
        });

        Event::listen(StoreSaved::class, static function(StoreSaved $event) {
            $legacy = Plugin::getInstance()->getStores();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_SAVE_STORE)) {
                $legacy->trigger(self::EVENT_AFTER_SAVE_STORE, $event);
            }
        });

        Event::listen(StoreDeleting::class, static function(StoreDeleting $event) {
            $legacy = Plugin::getInstance()->getStores();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_DELETE_STORE)) {
                $legacy->trigger(self::EVENT_BEFORE_DELETE_STORE, $event);
            }
        });

        Event::listen(StoreDeleteApplying::class, static function(StoreDeleteApplying $event) {
            $legacy = Plugin::getInstance()->getStores();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_APPLY_STORE_DELETE)) {
                $legacy->trigger(self::EVENT_BEFORE_APPLY_STORE_DELETE, $event);
            }
        });

        Event::listen(StoreDeleted::class, static function(StoreDeleted $event) {
            $legacy = Plugin::getInstance()->getStores();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_DELETE_STORE)) {
                $legacy->trigger(self::EVENT_AFTER_DELETE_STORE, $event);
            }
        });
    }
}
