<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
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
use yii\base\ErrorException;
use yii\base\InvalidConfigException;

/**
 * Inventory Locations service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class InventoryLocations extends Component
{
    /**
     * @var Collection<InventoryLocation>|null All locations
     */
    private ?Collection $_allLocations = null;

    /**
     * Returns all inventory locations.
     *
     * @return Collection All locations
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getAllInventoryLocations(): Collection
    {
        return $this->_getAllInventoryLocations();
    }

    /**
     * Returns an inventory location by its ID.
     *
     * @param int $id
     * @return InventoryLocation|null The inventory location or null if not found.
     */
    public function getInventoryLocationById(int $id): ?InventoryLocation
    {
        return $this->_getAllInventoryLocations()->firstWhere('id', $id);
    }

    /**
     * Gets all inventory locations for a store in order of configuration.
     *
     * @param ?int $store
     *
     * @return Collection<InventoryLocation>
     */
    public function getInventoryLocations(?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $locationIds = (new Query())
            ->select(['inventoryLocationId'])
            ->from([Table::INVENTORYLOCATIONS_STORES])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->where(['storeId' => $storeId])
            ->column();

        // Keep the order of the locationIds
        return $this->_getAllInventoryLocations()->whereIn('id', $locationIds)->sortBy(function($inventoryLocation) use ($locationIds) {
            return array_search($inventoryLocation->id, $locationIds);
        });
    }

    /**
     * Returns the primary (first) inventory location for a store.
     *
     * @param ?int $storeId
     * @return InventoryLocation
     */
    public function getPrimaryInventoryLocation(int $storeId = null): InventoryLocation
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $locationId = (new Query())
            ->select(['inventoryLocationId'])
            ->from([Table::INVENTORYLOCATIONS_STORES])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->where(['storeId' => $storeId])
            ->scalar();

        return $this->_getAllInventoryLocations()->firstWhere('id', $locationId);
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
     * @param int $id
     * @return bool
     * @throws Throwable
     * @throws \yii\db\Exception
     */
    public function deleteInventoryLocationById(int $id): bool
    {
        $location = $this->getInventoryLocationById($id);

        if (!$location) {
            return false;
        }

        if ($this->getAllInventoryLocations()->count() === 1) {
            throw new ErrorException('You cannot delete the last inventory location.');
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            Craft::$app->getDb()->createCommand()
                ->delete(Table::INVENTORYLOCATIONS, ['id' => $id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $this->_allLocations = null;

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

            /** @var InventoryLocationRecord $locationRecord */
            $locationRecord = InventoryLocationRecord::find()
                ->where(['id' => $inventoryLocation->id])
                ->one();

            if (!$locationRecord) {
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

        $this->_allLocations = null; // reset cache

        return true;
    }

    /**
     * Returns a Query object prepped for retrieving locations.
     *
     * @return Query The query object.
     */
    private function _createLocationsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'addressId',
                'dateCreated',
                'dateUpdated',
            ])
            ->from([Table::INVENTORYLOCATIONS]);
    }

    /**
     * @return Collection<InventoryLocation>
     */
    private function _getAllInventoryLocations(): Collection
    {
        if ($this->_allLocations === null) {
            $results = $this->_createLocationsQuery()
                ->all();

            $locations = [];
            foreach ($results as $result) {
                $locations[] = new InventoryLocation($result);
            }

            $this->_allLocations = collect($locations);
        }

        return $this->_allLocations;
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

        if ($this->getAllInventoryLocations()->firstWhere('addressId', $event->element->getCanonicalId()) === null) {
            return;
        }

        $event->authorized = true;
    }

    public function authorizeInventoryLocationAddressEdit(AuthorizationCheckEvent $event): void
    {
        if (!$event->element instanceof Address) {
            return;
        }

        if ($this->getAllInventoryLocations()->firstWhere('addressId', $event->element->getCanonicalId()) === null) {
            return;
        }

        $event->authorized = true;
    }
}
