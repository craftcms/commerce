<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\events\DeleteStoreEvent;
use craft\commerce\events\StoreEvent;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\SiteStore;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentCurrency;
use craft\commerce\records\ShippingCategory;
use craft\commerce\records\SiteStore as SiteStoreRecord;
use craft\commerce\records\Store as StoreRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\Address;
use craft\errors\BusyResourceException;
use craft\errors\SiteNotFoundException;
use craft\errors\StaleResourceException;
use craft\events\ConfigEvent;
use craft\events\SiteEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\Site;
use Exception;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\Exception as YiiDbException;
use yii\db\Expression;
use yii\web\ServerErrorHttpException;

/**
 * Stores service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 *
 * @property-read Store $primaryStore
 * @property-read Collection $allStores
 */
class Stores extends Component
{
    /**
     * @event DeleteStoreEvent The event that is triggered before a site is deleted.
     *
     * You may set [[\craft\events\CancelableEvent::$isValid]] to `false` to prevent the site from getting deleted.
     */
    public const EVENT_BEFORE_DELETE_STORE = 'beforeDeleteStore';

    /**
     * @event DeleteStoreEvent The event that is triggered after a store is deleted
     */
    public const EVENT_AFTER_DELETE_STORE = 'afterDeleteStore';

    /**
     * @event DeleteStoreEvent The event that is triggered before a store delete is applied to the database.
     */
    public const EVENT_BEFORE_APPLY_STORE_DELETE = 'beforeApplyStoreDelete';

    /**
     * @event StoreEvent The event that is triggered before a store is saved.
     */
    public const EVENT_BEFORE_SAVE_STORE = 'beforeSaveStore';

    /**
     * @event StoreEvent The event that is triggered after a store is saved.
     */
    public const EVENT_AFTER_SAVE_STORE = 'afterSaveStore';

    /**
     * The project config path to stores data
     */
    public const CONFIG_STORES_KEY = 'commerce.stores';

    /**
     * The project config path to site stores data
     */
    public const CONFIG_SITESTORES_KEY = 'commerce.sitestores';

    /**
     * @var Collection<Store>|null
     */
    private ?Collection $_allStores = null;

    /**
     * @var Collection<store>|null
     */
    private ?Collection $_allStoresBySiteId = null;

    /**
     * @var Collection|null
     */
    private ?Collection $_allSiteStores = null;

    /**
     * @return void
     */
    private function _loadAllStores(): void
    {
        if (isset($this->_allStores)) {
            return;
        }

        $results = $this->_createStoreQuery()->all();
        $siteStores = $this->_createSiteStoresQuery()
            ->select(['storeId', 'siteId'])
            ->all();

        $allStores = [];
        $allStoresBySiteId = [];

        foreach ($results as $row) {
            $store = Craft::createObject(array_merge(['class' => Store::class], $row));

            $allStores[] = $store;

            foreach (ArrayHelper::where($siteStores, 'storeId', $store->id) as $siteStore) {
                $allStoresBySiteId[$siteStore['siteId']] = $store;
            }
        }

        $this->_allStores = collect($allStores);
        $this->_allStoresBySiteId = collect($allStoresBySiteId);
    }

    /**
     * Returns the current store.
     *
     * @return Store the current store
     * @throws SiteNotFoundException
     */
    public function getCurrentStore(): Store
    {
        return $this->getStoreBySiteId(Craft::$app->getSites()->getCurrentSite()->id) ?? $this->getPrimaryStore();
    }

    /**
     * @return Collection<Store>
     */
    public function getAllStores(): Collection
    {
        if ($this->_allStores === null) {
            $this->_loadAllStores();
        }

        return $this->_allStores ?? collect();
    }

    /**
     * @param int $id
     * @return Store|null
     */
    public function getStoreById(int $id): ?Store
    {
        return $this->getAllStores()->firstWhere('id', $id);
    }

    /**
     * @param string $uid
     * @return Store|null
     */
    public function getStoreByUid(string $uid): ?Store
    {
        return $this->getAllStores()->firstWhere('uid', $uid);
    }

    /**
     * @param int $siteId
     * @return Store|null
     */
    public function getStoreBySiteId(int $siteId): ?Store
    {
        if ($this->_allStoresBySiteId === null) {
            // Population of `_allStoresBySiteId` is done in `_loadAllStores()`
            $this->_loadAllStores();
        }

        return $this->_allStoresBySiteId?->get($siteId);
    }

    /**
     * @param string $handle
     * @return Store|null
     */
    public function getStoreByHandle(string $handle): ?Store
    {
        return $this->getAllStores()->firstWhere('handle', $handle);
    }

    /**
     * Returns a collections of stores that are available to a user.
     *
     * @param int $userId
     * @return Collection<Store>
     * @throws InvalidConfigException
     */
    public function getStoresByUserId(int $userId): Collection
    {
        $user = Craft::$app->getUsers()->getUserById($userId);

        if (!$user) {
            throw new InvalidConfigException('Invalid user ID: ' . $userId);
        }

        $allStores = $this->getAllStores();
        if (!Craft::$app->getIsMultiSite()) {
            return $allStores;
        }

        return $allStores->filter(function(Store $store) use ($user) {
            $siteUids = $store->getSites()->map(fn(Site $site) => $site->uid);

            foreach ($siteUids as $siteUid) {
                if ($user->can('editSite:' . $siteUid)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Saves a store.
     *
     * @param Store $store The store to be saved
     * @param bool $runValidation Whether the store should be validated
     * @return bool
     * @throws BusyResourceException
     * @throws StaleResourceException
     * @throws ErrorException
     * @throws YiiBaseException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function saveStore(Store $store, bool $runValidation = true): bool
    {
        $isNewStore = !$store->id;

        // Fire a 'beforeSaveStore' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_STORE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_STORE, new StoreEvent([
                'store' => $store,
                'isNew' => $isNewStore,
            ]));
        }

        if ($runValidation && !$store->validate()) {
            Craft::info('Store not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewStore) {
            $store->uid = StringHelper::UUID();
        } elseif (!$store->uid) {
            $store->uid = Db::uidById(Table::STORES, $store->id);
        }

        $projectConfigService = Craft::$app->getProjectConfig();
        $configPath = self::CONFIG_STORES_KEY . "." . $store->uid;
        $projectConfigService->set(
            $configPath,
            $store->getConfig(),
            "Save the “{$store->handle}” store"
        );

        // Now that we have a store ID, save it on the model
        if ($isNewStore) {
            $store->id = Db::idByUid(Table::STORES, $store->uid);

            // Create any default data we need for the store
            $orderStatus = Craft::createObject([
                'class' => OrderStatus::class,
                'attributes' => [
                    'name' => 'New',
                    'handle' => 'new',
                    'color' => 'green',
                    'default' => true,
                    'storeId' => $store->id,
                ],
            ]);
            Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus);
        }

        // Update the other primary store.
        if ($store->primary) {
            foreach ($projectConfigService->get(self::CONFIG_STORES_KEY) as $uid => $config) {
                if ($uid !== $store->uid && isset($config['primary']) && $config['primary'] === true) {
                    $configPath = self::CONFIG_STORES_KEY . '.' . $uid;
                    $config['primary'] = false; // Set the other to false
                    $projectConfigService->set(
                        $configPath,
                        $config,
                        "Set the “{$config['name']}” store to not be primary"
                    );
                }
            }
        }

        $this->refreshStores();

        return true;
    }

    /**
     * @param int $storeId
     * @return bool
     * @throws Exception
     */
    public function deleteStoreById(int $storeId): bool
    {
        $store = $this->getStoreById($storeId);

        if (!$store) {
            return false;
        }

        return $this->deleteStore($store);
    }

    /**
     * @param Store $store
     * @return bool
     * @throws Exception
     */
    public function deleteStore(Store $store): bool
    {
        // Make sure this isn't the primary site
        if ($store->id === $this->getPrimaryStore()?->id) {
            throw new Exception('You cannot delete the primary store.');
        }

        // Fire a 'beforeDeleteStore' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_STORE)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_STORE, new DeleteStoreEvent([
                'store' => $store,
            ]));
        }

        $path = self::CONFIG_STORES_KEY . '.' . $store->uid;
        Craft::$app->getProjectConfig()->remove($path, "Delete the “{$store->handle}” store");

        return true;
    }

    /**
     * Handle store status change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable
     * @throws YiiDbException
     */
    public function handleChangedStore(ConfigEvent $event): void
    {
        $storeUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $storeRecord = $this->_getStoreRecord($storeUid);
            $isNewStore = $storeRecord->getIsNewRecord();

            $storeRecord->uid = $storeUid;
            $storeRecord->name = $data['name'];
            $storeRecord->handle = $data['handle'];
            $storeRecord->primary = $data['primary'];

            $storeRecord->autoSetNewCartAddresses = ($data['autoSetNewCartAddresses'] ?? false);
            $storeRecord->autoSetCartShippingMethodOption = ($data['autoSetCartShippingMethodOption'] ?? false);
            $storeRecord->autoSetPaymentSource = ($data['autoSetPaymentSource'] ?? false);
            $storeRecord->allowEmptyCartOnCheckout = ($data['allowEmptyCartOnCheckout'] ?? false);
            $storeRecord->allowCheckoutWithoutPayment = ($data['allowCheckoutWithoutPayment'] ?? false);
            $storeRecord->allowPartialPaymentOnCheckout = ($data['allowPartialPaymentOnCheckout'] ?? false);
            $storeRecord->requireShippingAddressAtCheckout = ($data['requireShippingAddressAtCheckout'] ?? false);
            $storeRecord->requireBillingAddressAtCheckout = ($data['requireBillingAddressAtCheckout'] ?? false);
            $storeRecord->requireShippingMethodSelectionAtCheckout = ($data['requireShippingMethodSelectionAtCheckout'] ?? false);
            $storeRecord->useBillingAddressForTax = ($data['useBillingAddressForTax'] ?? false);
            $storeRecord->validateOrganizationTaxIdAsVatId = ($data['validateOrganizationTaxIdAsVatId'] ?? false);
            $storeRecord->freeOrderPaymentStrategy = ($data['freeOrderPaymentStrategy'] ?? 'complete');
            $storeRecord->minimumTotalPriceStrategy = ($data['minimumTotalPriceStrategy'] ?? 'default');
            $storeRecord->orderReferenceFormat = ($data['orderReferenceFormat'] ?? '{{number[:7]}}');
            $storeRecord->currency = ($data['currency'] ?? null);
            $storeRecord->sortOrder = ($data['sortOrder'] ?? 99);

            $storeRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Did the primary site just change?
        if ($data['primary']) {
            Db::update(Table::STORES, ['primary' => false], ['not', ['id' => $storeRecord->id]]);
            Db::update(Table::STORES, ['primary' => true], ['id' => $storeRecord->id]);
        }

        $paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($data['currency'] ?? '', $storeRecord->id);
        if (!$paymentCurrency) {
            $data = [
                'iso' => $data['currency'] ?? 'USD',
                'storeId' => $storeRecord->id,
                'rate' => 1,
            ];
            Craft::$app->getDb()->createCommand()->insert(PaymentCurrency::tableName(), $data)->execute();
        }

        if (Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($storeRecord->id)->isEmpty()) {
            $data = [
                'name' => 'General',
                'handle' => 'general',
                'default' => true,
                'storeId' => $storeRecord->id,
            ];
            Craft::$app->getDb()->createCommand()->insert(ShippingCategory::tableName(), $data)->execute();
        }

        $this->refreshStores();

        // Fire a 'afterSaveStore' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_STORE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_STORE, new StoreEvent([
                'store' => $this->getStoreById($storeRecord->id),
                'isNew' => $isNewStore,
            ]));
        }
    }

    /**
     * Handle a deleted Store.
     *
     * @param ConfigEvent $event
     * @throws Throwable
     * @throws YiiDbException
     */
    public function handleDeletedStore(ConfigEvent $event): void
    {
        $storeUid = $event->tokenMatches[0];
        $storeRecord = $this->_getStoreRecord($storeUid);

        if (!$storeRecord->id) {
            return;
        }

        /** @var Store $store */
        $store = $this->getStoreById($storeRecord->id);

        // Fire a 'beforeApplyStoreDelete' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_APPLY_STORE_DELETE)) {
            $this->trigger(self::EVENT_BEFORE_APPLY_STORE_DELETE, new DeleteStoreEvent([
                'store' => $store,
            ]));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $locationAddressId = $store->getSettings()->getLocationAddressId();

            Craft::$app->getDb()->createCommand()
                ->delete(Table::STORES, ['id' => $storeRecord->id])
                ->execute();

            // Delete store address
            if ($locationAddressId) {
                Craft::$app->getElements()->deleteElementById($locationAddressId, Address::class, hardDelete: true);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Refresh stores
        $this->refreshStores();

        // Make sure any site store for this store is reassigned to the primary store
        $siteStores = collect($this->getAllSiteStores())->where('storeId', $store->id)->all();
        foreach ($siteStores as $siteStore) {
            $siteStore->storeId = $this->getPrimaryStore()->id;
            $this->saveSiteStore($siteStore);
        }

        // Fire an 'afterDeleteStore' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_STORE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_STORE, new DeleteStoreEvent([
                'store' => $store,
            ]));
        }
    }

    /**
     * Refresh the status of all stores based on the DB data.
     *
     * @return void
     */
    public function refreshStores(): void
    {
        $this->_allStores = null;
        $this->_allStoresBySiteId = null;
        $this->_loadAllStores();
    }

    /**
     * Returns the primary store.
     *
     * @return Store|null
     */
    public function getPrimaryStore(): ?Store
    {
        return $this->getAllStores()->firstWhere('primary', true);
    }

    /**
     * @param array $ids
     * @return bool
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws YiiBaseException
     */
    public function reorderStores(array $ids): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds(Table::STORES, $ids);

        foreach ($ids as $sortOrder => $id) {
            if (!empty($uidsByIds[$id])) {
                $uid = $uidsByIds[$id];
                $projectConfig->set(self::CONFIG_STORES_KEY . '.' . $uid . '.sortOrder', $sortOrder + 1);
            }
        }

        $this->refreshStores();

        return true;
    }

    /**
     * Gets a store record by uid.
     *
     * @param string $uid
     * @return StoreRecord
     */
    private function _getStoreRecord(string $uid): StoreRecord
    {
        if ($store = StoreRecord::findOne(['uid' => $uid])) {
            return $store;
        }

        return new StoreRecord();
    }

    /**
     * Returns a Query object prepped for retrieving the stores.
     *
     * @return Query
     */
    private function _createStoreQuery(): Query
    {
        $selectColumns = [
            'handle',
            'id',
            'name',
            'primary',
            'uid',
        ];

        // Added to avoid migration issues, as settings were moved after stores table creation
        // @TODO remove at next breaking change release
        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');

        if ($commerce && version_compare($commerce['schemaVersion'], '5.0.72', '>=')) {
            $selectColumns = array_merge($selectColumns, [
                'allowCheckoutWithoutPayment',
                'allowEmptyCartOnCheckout',
                'allowPartialPaymentOnCheckout',
                'autoSetCartShippingMethodOption',
                'autoSetNewCartAddresses',
                'autoSetPaymentSource',
                'currency',
                'freeOrderPaymentStrategy',
                'minimumTotalPriceStrategy',
                'orderReferenceFormat',
                'requireBillingAddressAtCheckout',
                'requireShippingAddressAtCheckout',
                'requireShippingMethodSelectionAtCheckout',
                'sortOrder',
                'useBillingAddressForTax',
                'validateOrganizationTaxIdAsVatId',
            ]);
        }

        $query = (new Query())
            ->select($selectColumns)
            ->from([Table::STORES]);

        if ($commerce && version_compare($commerce['schemaVersion'], '5.0.72', '>=')) {
            $query->orderBy(['sortOrder' => SORT_ASC]);
        }

        return $query;
    }

    /**
     * @param Store $store
     * @return Collection<Site>
     */
    public function getAllSitesForStore(Store $store): Collection
    {
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->getAllSiteStores()
            ->filter(fn(SiteStore $siteStore) => $siteStore->storeId == $store->id)
            ->map(fn(SiteStore $siteStore) => ArrayHelper::firstWhere($sites, 'id', $siteStore->siteId));
    }

    /**
     * @return Collection<SiteStore>
     */
    public function getAllSiteStores(): Collection
    {
        if ($this->_allSiteStores !== null) {
            return $this->_allSiteStores;
        }

        $siteStores = [];
        foreach ($this->_createSiteStoresQuery()->all() as $store) {
            $siteStores[] = new SiteStore($store);
        }

        return !empty($siteStores) ? $this->_allSiteStores = collect($siteStores) : collect();
    }

    /**
     * Returns sites that are assigned to more than one store assigned, so that other new stores can use them.
     *
     * @return array
     */
    public function getSiteIdsAvailableForAssignmentToNewStores(): array
    {
        // Sites that are assigned to more than one store
        $subQuery = (new Query())
            ->select('storeId')
            ->from(Table::SITESTORES)
            ->groupBy('storeId')
            ->having(['>', new Expression('COUNT([[storeId]])'), 1]);

        return (new Query())
            ->select('siteId')
            ->from(Table::SITESTORES)
            ->where(['IN', 'storeId', $subQuery])
            ->groupBy('siteId')
            ->column();
    }

    /**
     * @param SiteStore $siteStore
     * @param bool $runValidation
     * @return bool
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws YiiBaseException
     */
    public function saveSiteStore(SiteStore $siteStore, bool $runValidation = true): bool
    {
        if ($runValidation && !$siteStore->validate()) {
            Craft::info('Site store mapping not saved due to validation error.', __METHOD__);
            return false;
        }

        // We use the same UID as the site since we only have one record per site.
        // This also makes it easier to see what site a store is mapped to in the project config.
        $craftSite = Craft::$app->getSites()->getSiteById($siteStore->siteId);
        if (!$craftSite) {
            throw new InvalidConfigException('Invalid site ID: ' . $siteStore->siteId);
        }

        if (!$siteStore->uid) {
            $siteStore->uid = Db::uidById(CraftTable::SITES, $siteStore->siteId);
        }

        $projectConfigService = Craft::$app->getProjectConfig();
        $configPath = self::CONFIG_SITESTORES_KEY . "." . $siteStore->uid;
        $projectConfigService->set(
            $configPath,
            $siteStore->getConfig(),
            "Save the “{$craftSite->handle}” commerce site store mapping"
        );

        $this->refreshStores();

        return true;
    }

    /**
     * Handle site store mapping change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable
     * @throws YiiDbException
     */
    public function handleChangedSiteStore(ConfigEvent $event): void
    {
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigData::ensureAllStoresProcessed();

        $siteStoreUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $siteStoreRecord = SiteStoreRecord::findOne(['uid' => $siteStoreUid]);

            if (!$siteStoreRecord) {
                $siteStoreRecord = new SiteStoreRecord();
            }

            $siteStoreRecord->siteId = Db::idByUid(CraftTable::SITES, $siteStoreUid);
            $siteStoreRecord->storeId = Db::idByUid(Table::STORES, $data['store']);
            $siteStoreRecord->uid = $siteStoreUid;

            $siteStoreRecord->save(false);

            $transaction->commit();

            $this->refreshStores();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Handle a deleted Store.
     *
     * @param ConfigEvent $event
     * @throws Throwable
     * @throws YiiDbException
     */
    public function handleDeletedSiteStore(ConfigEvent $event): void
    {
        $storeStoreUid = $event->tokenMatches[0];
        $siteStoreRecord = SiteStoreRecord::findOne(['uid' => $storeStoreUid]); // site_stores uses the site UID

        if (!$siteStoreRecord) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            Craft::$app->getDb()->createCommand()
                ->delete(Table::SITESTORES, ['siteId' => $siteStoreRecord->siteId])
                ->execute();

            $transaction->commit();

            $this->refreshStores();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     *
     * @param SiteEvent $event
     * @return void
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws YiiBaseException
     */
    public function afterSaveCraftSiteHandler(SiteEvent $event): void
    {
        $siteStore = SiteStoreRecord::findOne(['siteId' => $event->site->id]);

        // Only create it if it doesn't exist.
        // The saving of the store does not currently change the store relation, but if it did,
        // we would need to mutate the existing record.
        if (!$siteStore) {
            $siteStore = new SiteStore();
            $siteStore->siteId = $event->site->id;
            $siteStore->storeId = $this->getPrimaryStore()->id;
            $siteStore->uid = $event->site->uid;
            $this->saveSiteStore($siteStore);
        }
    }

    /**
     * @param SiteEvent $event
     * @return void
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws YiiBaseException
     */
    public function afterDeleteCraftSiteHandler(SiteEvent $event): void
    {
        $siteStores = $this->getAllSiteStores();
        $siteStore = $this->getAllSiteStores()->firstWhere('siteId', $event->site->id);
        $store = $this->getStoreById($siteStore->storeId);

        $isStoreOrphaned = true;
        foreach ($siteStores as $ss) {
            if ($ss->siteId !== $siteStore->siteId && $ss->storeId === $siteStore->storeId) {
                $isStoreOrphaned = false;
                break;
            }
        }

        // If this was the primary store, make another the primary
        if ($store->primary && $isStoreOrphaned) {
            // make another store primary
            $store = $this->getAllStores()->firstWhere('primary', false);
            $store->primary = true;
            $this->saveStore($store);
        }

        // Delete the old siteStore record
        Craft::$app->getProjectConfig()->remove(self::CONFIG_SITESTORES_KEY . '.' . $siteStore->uid);
    }

    /**
     * @return Query
     */
    private function _createSiteStoresQuery(): Query
    {
        // get the site stores
        return (new Query())
            ->select([
                'siteId',
                'storeId',
                'uid',
            ])
            ->from([Table::SITESTORES]);
    }
}
