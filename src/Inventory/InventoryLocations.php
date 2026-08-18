<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory;

use craft\commerce\models\inventory\InventoryLocationDeactivatedMovement;
use craft\events\AuthorizationCheckEvent;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Models\DeactivateInventoryLocation;
use CraftCms\Commerce\Inventory\Models\InventoryLevel;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use CraftCms\Commerce\Inventory\Records\InventoryLocation as InventoryLocationRecord;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use function CraftCms\Cms\t;

#[Singleton]
class InventoryLocations
{
    /** @var Collection<int, InventoryLocation>|null */
    private ?Collection $allLocations = null;

    /** @var Collection<int, InventoryLocation>|null */
    private ?Collection $allLocationsWithTrashed = null;

    /**
     * @return Collection<int, InventoryLocation>
     */
    public function getAllInventoryLocations(bool $withTrashed = false): Collection
    {
        return $this->fetchAllInventoryLocations($withTrashed);
    }

    /** @return array<int, string> */
    public function getAllInventoryLocationsAsList(bool $withTrashed = false): array
    {
        return $this->getAllInventoryLocations($withTrashed)->mapWithKeys(fn(InventoryLocation $location) => [$location->id => $location->getUiLabel()])->toArray();
    }

    public function getInventoryLocationById(int $id, bool $withTrashed = false): ?InventoryLocation
    {
        return $this->fetchAllInventoryLocations($withTrashed)->firstWhere('id', $id);
    }

    /**
     * Gets all inventory locations for a store in order of configuration.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getInventoryLocations(?int $storeId = null, bool $withTrashed = false): Collection
    {
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        $locationIds = DB::table(Table::INVENTORYLOCATIONS_STORES)
            ->select('inventoryLocationId')
            ->where('storeId', $storeId)
            ->orderBy('sortOrder')
            ->pluck('inventoryLocationId')
            ->all();

        // Keep the order of the locationIds
        return $this->fetchAllInventoryLocations($withTrashed)->whereIn('id', $locationIds)->sortBy(fn($inventoryLocation) => array_search($inventoryLocation->id, $locationIds));
    }

    /**
     * Stores the relationship between a Store and its Inventory Locations, ordered by preference.
     *
     * @param int[] $inventoryLocationIds
     */
    public function saveStoreInventoryLocations(Store $store, array $inventoryLocationIds): bool
    {
        DB::beginTransaction();

        try {
            DB::table(Table::INVENTORYLOCATIONS_STORES)->where('storeId', $store->id)->delete();

            $order = 1;
            foreach ($inventoryLocationIds as $inventoryLocationId) {
                DB::table(Table::INVENTORYLOCATIONS_STORES)->insert([
                    'storeId' => $store->id,
                    'inventoryLocationId' => $inventoryLocationId,
                    'sortOrder' => $order++,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function executeDeactivateInventoryLocation(DeactivateInventoryLocation $deactivateInventoryLocation): bool
    {
        // This will ensure that the location has no committed stock or incoming stock before deactivating it.
        if (!$deactivateInventoryLocation->validate()) {
            return false;
        }

        DB::beginTransaction();

        try {
            $inventoryLocationRecord = InventoryLocationRecord::find($deactivateInventoryLocation->inventoryLocation->id);

            // TODO: Add draft purchase order swapping

            $inventoryLevels = app(Inventory::class)->getInventoryLocationLevels($deactivateInventoryLocation->inventoryLocation);
            /** @var InventoryLevel $inventoryLevel */
            foreach ($inventoryLevels as $inventoryLevel) {
                $movements = new InventoryMovementCollection();
                foreach (InventoryTransactionType::allowedManualMoveTransactionTypes() as $type) {
                    if ($inventoryLevel->getTotal($type) > 0) {
                        $inventoryMovement = new InventoryLocationDeactivatedMovement();
                        $inventoryMovement->fromInventoryLocation = $deactivateInventoryLocation->inventoryLocation;
                        $inventoryMovement->toInventoryLocation = $deactivateInventoryLocation->destinationInventoryLocation;
                        $inventoryMovement->inventoryItemId = $inventoryLevel->inventoryItemId;
                        $inventoryMovement->quantity = $inventoryLevel->getTotal($type);
                        $inventoryMovement->fromInventoryTransactionType = $type;
                        $inventoryMovement->toInventoryTransactionType = $type;
                        $inventoryMovement->userId = request()->craftUser()?->id;
                        $inventoryMovement->note = t('Movement from deactivated inventory location', category: 'commerce');
                        $movements->add($inventoryMovement);
                    }
                }

                if ($movements->count() > 0) {
                    if (!app(Inventory::class)->executeInventoryMovements($movements)) {
                        throw new RuntimeException('Failed to move inventory from deactivated location');
                    }
                }
            }

            // Finally soft delete it now that it's all migrated
            $inventoryLocationRecord->delete();

            DB::commit();

            $this->clearCache();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function getInventoryLocationByHandle(string $handle): ?InventoryLocation
    {
        return $this->getAllInventoryLocations()->firstWhere('handle', $handle);
    }

    public function saveInventoryLocation(InventoryLocation $inventoryLocation, bool $runValidation = true): bool
    {
        $isNewLocation = !$inventoryLocation->id;

        if ($runValidation && !$inventoryLocation->validate()) {
            Log::info('Inventory Location not saved due to validation error.');
            return false;
        }

        DB::beginTransaction();

        try {
            $locationRecord = InventoryLocationRecord::find($inventoryLocation->id);

            if ($locationRecord === null) {
                $locationRecord = new InventoryLocationRecord();
            }

            $locationRecord->name = $inventoryLocation->name;
            $locationRecord->handle = $inventoryLocation->handle;
            $locationRecord->addressId = $inventoryLocation->getAddress()->id;

            $locationRecord->save();

            if ($isNewLocation) {
                $inventoryLocation->id = $locationRecord->id;
            }

            DB::commit();

            $this->clearCache();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function authorizeInventoryLocationAddressView(AuthorizationCheckEvent $event): void
    {
        if (!$event->element instanceof Address) {
            return;
        }

        if ($this->getAllInventoryLocations(true)->firstWhere('addressId', $event->element->getCanonicalId()) === null) {
            return;
        }

        $event->authorized = true;
    }

    public function authorizeInventoryLocationAddressEdit(AuthorizationCheckEvent $event): void
    {
        if (!$event->element instanceof Address) {
            return;
        }

        if ($this->getAllInventoryLocations(true)->firstWhere('addressId', $event->element->getCanonicalId()) === null) {
            return;
        }

        $event->authorized = true;
    }

    private function clearCache(): void
    {
        $this->allLocations = null;
        $this->allLocationsWithTrashed = null;
    }

    private function query(bool $withTrashed = false): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(Table::INVENTORYLOCATIONS)
            ->select([
                'id',
                'name',
                'handle',
                'addressId',
                'dateCreated',
                'dateUpdated',
            ])
            ->orderBy('name');

        if (!$withTrashed) {
            $query->whereNull('dateDeleted');
        }

        return $query;
    }

    /**
     * @return Collection<int, InventoryLocation>
     */
    private function fetchAllInventoryLocations(bool $withTrashed = false): Collection
    {
        if ($withTrashed) {
            if ($this->allLocationsWithTrashed === null) {
                $this->allLocationsWithTrashed = $this->query(true)->get()->map(fn($row) => new InventoryLocation((array) $row));
            }

            return $this->allLocationsWithTrashed;
        }

        if ($this->allLocations === null) {
            $this->allLocations = $this->query(false)->get()->map(fn($row) => new InventoryLocation((array) $row));
        }

        return $this->allLocations;
    }
}
