<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Inventory\Models\DeactivateInventoryLocation;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use CraftCms\Commerce\Store\Models\Store;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Inventory\InventoryLocations::class)` instead.
 */
class InventoryLocations extends Component
{
    /**
     * @return Collection<InventoryLocation>
     */
    public function getAllInventoryLocations(bool $withTrashed = false): Collection
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->getAllInventoryLocations($withTrashed);
    }

    public function getAllInventoryLocationsAsList(bool $withTrashed = false): array
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->getAllInventoryLocationsAsList($withTrashed);
    }

    public function getInventoryLocationById(int $id, bool $withTrashed = false): ?InventoryLocation
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->getInventoryLocationById($id, $withTrashed);
    }

    /**
     * @return Collection<InventoryLocation>
     */
    public function getInventoryLocations(?int $storeId = null, bool $withTrashed = false): Collection
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->getInventoryLocations($storeId, $withTrashed);
    }

    public function saveStoreInventoryLocations(Store $store, array $inventoryLocationIds): bool
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->saveStoreInventoryLocations($store, $inventoryLocationIds);
    }

    public function executeDeactivateInventoryLocation(DeactivateInventoryLocation $deactivateInventoryLocation): bool
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->executeDeactivateInventoryLocation($deactivateInventoryLocation);
    }

    public function getInventoryLocationByHandle(string $handle): ?InventoryLocation
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->getInventoryLocationByHandle($handle);
    }

    public function saveInventoryLocation(InventoryLocation $inventoryLocation, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->saveInventoryLocation($inventoryLocation, $runValidation);
    }
}
