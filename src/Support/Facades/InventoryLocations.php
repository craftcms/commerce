<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllInventoryLocations(bool $withTrashed = false)
 * @method static array getAllInventoryLocationsAsList(bool $withTrashed = false)
 * @method static \CraftCms\Commerce\Inventory\Data\InventoryLocation|null getInventoryLocationById(int $id, bool $withTrashed = false)
 * @method static \Illuminate\Support\Collection getInventoryLocations(int|null $storeId = null, bool $withTrashed = false)
 * @method static bool saveStoreInventoryLocations(\CraftCms\Commerce\Store\Data\Store $store, int[] $inventoryLocationIds)
 * @method static bool executeDeactivateInventoryLocation(\CraftCms\Commerce\Inventory\Data\DeactivateInventoryLocation $deactivateInventoryLocation)
 * @method static bool canCreateInventoryLocation()
 * @method static \CraftCms\Commerce\Inventory\Data\InventoryLocation|null getInventoryLocationByHandle(string $handle)
 * @method static bool saveInventoryLocation(\CraftCms\Commerce\Inventory\Data\InventoryLocation $inventoryLocation, bool $runValidation = true)
 * @method static void authorizeInventoryLocationAddressView(\CraftCms\Cms\Auth\Events\ElementAuthorizing $event)
 * @method static void authorizeInventoryLocationAddressEdit(\CraftCms\Cms\Auth\Events\ElementAuthorizing $event)
 *
 * @see \CraftCms\Commerce\Inventory\InventoryLocations
 */
class InventoryLocations extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Inventory\InventoryLocations::class;
    }
}
