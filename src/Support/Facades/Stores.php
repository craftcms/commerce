<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Store\Data\Store getCurrentStore()
 * @method static \Illuminate\Support\Collection getAllStores()
 * @method static \CraftCms\Commerce\Store\Data\Store|null getStoreById(int $id)
 * @method static \CraftCms\Commerce\Store\Data\Store|null getStoreByUid(string $uid)
 * @method static \CraftCms\Commerce\Store\Data\Store|null getStoreBySiteId(int $siteId)
 * @method static \CraftCms\Commerce\Store\Data\Store|null getStoreByHandle(string $handle)
 * @method static \Illuminate\Support\Collection getStoresByUserId(int $userId)
 * @method static bool saveStore(\CraftCms\Commerce\Store\Data\Store $store, bool $runValidation = true)
 * @method static bool deleteStoreById(int $storeId)
 * @method static bool deleteStore(\CraftCms\Commerce\Store\Data\Store $store)
 * @method static void handleChangedStore(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void handleDeletedStore(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void refreshStores()
 * @method static \CraftCms\Commerce\Store\Data\Store|null getPrimaryStore()
 * @method static bool reorderStores(int[] $ids)
 * @method static \Illuminate\Support\Collection getAllSitesForStore(\CraftCms\Commerce\Store\Data\Store $store)
 * @method static \Illuminate\Support\Collection getAllSiteStores()
 * @method static array getSiteIdsAvailableForAssignmentToNewStores()
 * @method static bool saveSiteStore(\CraftCms\Commerce\Store\Data\SiteStore $siteStore, bool $runValidation = true)
 * @method static void handleChangedSiteStore(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void handleDeletedSiteStore(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void afterSaveCraftSiteHandler(\CraftCms\Cms\Site\Events\SiteSaved $event)
 * @method static void afterDeleteCraftSiteHandler(\CraftCms\Cms\Site\Events\SiteDeleted $event)
 *
 * @see \CraftCms\Commerce\Store\Stores
 */
class Stores extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Store\Stores::class;
    }
}
