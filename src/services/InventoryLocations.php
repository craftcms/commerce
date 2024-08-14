<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\models\inventory\DeactivateInventoryLocation;
use craft\commerce\models\inventory\InventoryLocationDeactivatedMovement;
use craft\commerce\models\InventoryLevel;
use craft\commerce\models\InventoryLocation;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\InventoryLocation as InventoryLocationRecord;
use craft\db\Query;
use craft\elements\Address;
use craft\errors\DeprecationException;
use craft\events\AuthorizationCheckEvent;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * Inventory Locations service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class InventoryLocations extends Component
{
    /**
     * Returns all inventory locations.
     *
     * @param bool $withTrashed
     * @return Collection All locations
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getAllInventoryLocations(bool $withTrashed = false): Collection
    {
        return $this->_getAllInventoryLocations($withTrashed);
    }

    /**
     * Returns all inventory locations as a list.
     *
     * @param bool $withTrashed
     * @return array All locations as key value list
     * @throws DeprecationException
     * @throws InvalidConfigException
     * @since 5.1.0
     */
    public function getAllInventoryLocationsAsList(bool $withTrashed = false): array
    {
        return $this->getAllInventoryLocations($withTrashed)->mapWithKeys(function(InventoryLocation $location) {
            return [$location->id => $location->name];
        })->toArray();
    }

    /**
     * Returns an inventory location by its ID.
     *
     * @param int $id
     * @param bool $withTrashed
     * @return InventoryLocation|null The inventory location or null if not found.
     */
    public function getInventoryLocationById(int $id, bool $withTrashed = false): ?InventoryLocation
    {
        return $this->_getAllInventoryLocations($withTrashed)->firstWhere('id', $id);
    }

    /**
     * Gets all inventory locations for a store in order of configuration.
     *
     * @param ?int $storeId
     *
     * @return Collection<InventoryLocation>
     */
    public function getInventoryLocations(?int $storeId = null, bool $withTrashed = false): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $locationIds = (new Query())
            ->select(['inventoryLocationId'])
            ->from([Table::INVENTORYLOCATIONS_STORES])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->where(['storeId' => $storeId])
            ->column();

        // Keep the order of the locationIds
        return $this->_getAllInventoryLocations($withTrashed)->whereIn('id', $locationIds)->sortBy(function($inventoryLocation) use ($locationIds) {
            return array_search($inventoryLocation->id, $locationIds);
        });
    }

    /**
     * Stores the relationship between a Store and its Inventory Locations, ordered by preference.
     *
     * @param Store $store
     * @param array $inventoryLocationIds
     * @return bool
     * @throws Throwable
     * @throws \yii\db\Exception
     */
    public function saveStoreInventoryLocations(Store $store, array $inventoryLocationIds): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // Delete existing
            Craft::$app->getDb()->createCommand()
                ->delete(Table::INVENTORYLOCATIONS_STORES, ['storeId' => $store->id])
                ->execute();

            $order = 1;
            foreach ($inventoryLocationIds as $inventoryLocationId) {
                Craft::$app->getDb()->createCommand()
                    ->insert(Table::INVENTORYLOCATIONS_STORES, [
                        'storeId' => $store->id,
                        'inventoryLocationId' => $inventoryLocationId,
                        'sortOrder' => $order++,
                    ])
                    ->execute();
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * @return bool
     * @throws Throwable
     * @throws \yii\db\Exception
     */
    public function executeDeactivateInventoryLocation(DeactivateInventoryLocation $deactivateInventoryLocation): bool
    {
        // This will ensure that the location has no committed stock or incoming stock before deactivating it.
        if (!$deactivateInventoryLocation->validate()) {
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            /** @var SoftDeleteBehavior $inventoryLocationRecord */
            $inventoryLocationRecord = InventoryLocationRecord::findOne($deactivateInventoryLocation->inventoryLocation->id);

            // Get draft transfers that are destinations for the deactivated inventory location
//            /** @var Transfer $draftTransfers */
//            $draftTransfers = Transfer::find()
//                ->transferStatus(TransferStatusType::DRAFT)
//                ->destinationLocation($deactivateInventoryLocation->inventoryLocation)
//                ->all();

            // Switch the draft transfer to the new destination location
//            foreach ($draftTransfers as $draftTransfer) {
//                $draftTransfer->destinationLocationId = $deactivateInventoryLocation->destinationInventoryLocation->id;
//                Craft::$app->getElements()->saveElement($draftTransfer, false);
//            }

            // TODO: Add draft purchase order swapping

            $inventoryLevels = Plugin::getInstance()->getInventory()->getInventoryLocationLevels($deactivateInventoryLocation->inventoryLocation);
            /** @var InventoryLevel $inventoryLevel */
            foreach ($inventoryLevels as $inventoryLevel) {
                $movements = new InventoryMovementCollection();
                foreach (InventoryTransactionType::allowedManualMoveTransactionTypes() as $type) {
                    if ($inventoryLevel->getTotal($type) > 0) {
                        $inventoryMovement = new InventoryLocationDeactivatedMovement();
                        $inventoryMovement->fromInventoryLocation = $deactivateInventoryLocation->inventoryLocation;
                        $inventoryMovement->toInventoryLocation = $deactivateInventoryLocation->destinationInventoryLocation;
                        $inventoryMovement->inventoryItem = $inventoryLevel->getInventoryItem();
                        $inventoryMovement->quantity = $inventoryLevel->getTotal($type);
                        $inventoryMovement->fromInventoryTransactionType = $type;
                        $inventoryMovement->toInventoryTransactionType = $type;
                        $inventoryMovement->userId = Craft::$app->getUser()->getIdentity()?->id;
                        $inventoryMovement->note = Craft::t('commerce', 'Movement from deactivated inventory location');
                        $movements->add($inventoryMovement);
                    }
                }

                if ($movements->count() > 0) {
                    if (!Plugin::getInstance()->getInventory()->executeInventoryMovements($movements)) {
                        throw new \Exception('Failed to move inventory from deactivated location');
                    }
                }
            }

            $transaction->commit();
            // Finally soft delete it now that it’s all migrated
            $inventoryLocationRecord->softDelete();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }

    /**
     * Returns a location by its handle.
     *
     * @param string $handle
     * @return InventoryLocation|null The location or null if not found.
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getInventoryLocationByHandle(string $handle): ?InventoryLocation
    {
        return $this->getAllInventoryLocations()->firstWhere('handle', $handle);
    }

    /**
     * Saves an inventory location.
     *
     */
    public function saveInventoryLocation(InventoryLocation $inventoryLocation, bool $runValidation = true): bool
    {
        $isNewLocation = !$inventoryLocation->id;

        if ($runValidation && !$inventoryLocation->validate()) {
            Craft::info('Inventory Location not saved due to validation error.', __METHOD__);
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {

            /** @var ?InventoryLocationRecord $locationRecord */
            $locationRecord = InventoryLocationRecord::find()
                ->where(['id' => $inventoryLocation->id])
                ->one();

            if ($locationRecord === null) {
                $locationRecord = new InventoryLocationRecord();
            }

            $locationRecord->name = $inventoryLocation->name;
            $locationRecord->handle = $inventoryLocation->handle;
            $locationRecord->addressId = $inventoryLocation->getAddress()->id;

            // Save the inventory location
            $locationRecord->save(false);

            if ($isNewLocation) {
                $inventoryLocation->id = $locationRecord->id;
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Returns a Query object prepped for retrieving locations.
     *
     * @return Query The query object.
     */
    private function _createInventoryLocationsQuery(bool $withTrashed = false): Query
    {
        $query = (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'addressId',
                'dateCreated',
                'dateUpdated',
            ])
            ->from([Table::INVENTORYLOCATIONS]);

        if (!$withTrashed) {
            $query->where(['dateDeleted' => null]);
        }

        return $query;
    }

    /**
     * @return Collection<InventoryLocation>
     */
    private function _getAllInventoryLocations(bool $withTrashed = false): Collection
    {
        $results = $this->_createInventoryLocationsQuery($withTrashed)
            ->all();

        $locations = [];
        foreach ($results as $result) {
            $locations[] = new InventoryLocation($result);
        }

        return collect($locations);
    }

    /**
     * @param AuthorizationCheckEvent $event
     * @return void
     */
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
}
