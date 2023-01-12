<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\events\DeleteStoreEvent;
use craft\commerce\events\StoreEvent;
use craft\commerce\models\SiteSettings;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\SiteSettings as SiteSettingsRecord;
use craft\commerce\records\Store as StoreRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\events\ConfigEvent;
use craft\events\SiteEvent;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use Exception;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\Exception as YiiDbException;
use yii\web\ServerErrorHttpException;

/**
 * Stores service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
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
     * The project config path to site settings data
     */
    public const CONFIG_SITESETTINGS_KEY = 'commerce.sitesettings';

    /**
     * @var Collection<Store>|null
     */
    private ?Collection $_allStores = null;

    /**
     * @var Store|null the current store
     * @see getCurrentStore()
     * @see setCurrentStore()
     */
    private ?Store $_currentStore = null;

    /**
     * @return void
     */
    private function _loadAllStores(): void
    {
        if (isset($this->_allStores)) {
            return;
        }

        $results = $this->_createStoreQuery()->all();


        $this->_allStores = collect($results)->map(function($row) {
            return Craft::createObject(array_merge(['class' => Store::class], $row));
        });
    }

    /**
     * Returns the current store.
     *
     * @return Store the current store
     * @throws StoreNotFoundException if no stores exist
     */
    public function getCurrentStore(): Store
    {
        if (isset($this->_currentStore)) {
            return $this->_currentStore;
        }

        // Default to the primary store
        // TODO: Lookup the current default based on the current site.
        return $this->_currentStore = $this->getPrimaryStore();
    }

    /**
     * Sets the current store.
     *
     * @param Store|string|int|null $store the current store, or its handle/ID, or null
     * @throws InvalidArgumentException if $store is invalid
     */
    public function setCurrentStore(mixed $store): void
    {
        // In case this was called from the constructor...
        $this->_loadAllStores();

        if ($store === null) {
            $this->_currentStore = null;
            return;
        }

        if ($store instanceof Store) {
            $this->_currentStore = $store;
        } elseif (is_numeric($store)) {
            $this->_currentStore = $this->getStoreById($store, false);
        } else {
            $this->_currentStore = $this->getStoreByHandle($store, false);
        }

        // Did something go wrong?
        if (!$this->_currentStore) {
            // Fail silently if Craft isn't installed yet or is in the middle of updating
            if (Craft::$app->getIsInstalled() && !Craft::$app->getUpdates()->getIsCraftUpdatePending() && Plugin::getInstance()->getIsInstalled()) {
                throw new InvalidArgumentException('Invalid store: ' . $store);
            }
        }
    }

    /**
     * @return Collection<Store>
     */
    public function getAllStores(): Collection
    {
        if ($this->_allStores === null) {
            $this->_loadAllStores();
        }

        return $this->_allStores ?? collect([]);
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
     * @param string $handle
     * @return Store|null
     */
    public function getStoreByHandle(string $handle): ?Store
    {
        return $this->getAllStores()->firstWhere('handle', $handle);
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
        }

        foreach ($projectConfigService->get(self::CONFIG_STORES_KEY) as $uid => $config) {
            if ($uid !== $store->uid && $store->primary) {
                $configPath = self::CONFIG_STORES_KEY . '.' . $uid . '.primary';
                $projectConfigService->set(
                    $configPath,
                    false,
                    "Set the “{$config['name']}” store to not be primary"
                );
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

        Craft::$app->getProjectConfig()->remove(self::CONFIG_STORES_KEY . '.' . $store->uid);

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

            $storeRecord->name = $data['name'];
            $storeRecord->handle = $data['handle'];
            $storeRecord->primary = $data['primary'];

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
            Craft::$app->getDb()->createCommand()
                ->softDelete(Table::STORES, ['id' => $storeRecord->id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Refresh stores
        $this->refreshStores();

        // Was this the current store?
        if (isset($this->_currentStore) && $this->_currentStore->id == $store->id) {
            $this->setCurrentStore($this->getPrimaryStore());
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
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'primary',
                'uid',
            ])
            ->andWhere(['dateDeleted' => null])
            ->from([Table::STORES]);
    }

    // Sites
    public function saveSiteSettings(SiteSettings $siteSettings, bool $runValidation = true): bool
    {
        if ($runValidation && !$siteSettings->validate()) {
            Craft::info('Site settings not saved due to validation error.', __METHOD__);
            return false;
        }

        $craftSite = Craft::$app->getSites()->getSiteById($siteSettings->siteId);
        if (!$siteSettings->uid) {
            $siteSettings->uid = Db::uidById(CraftTable::SITES, $siteSettings->siteId);
        }

        $projectConfigService = Craft::$app->getProjectConfig();
        $configPath = self::CONFIG_SITESETTINGS_KEY . "." . $siteSettings->uid;
        $projectConfigService->set(
            $configPath,
            $siteSettings->getConfig(),
            "Save the “{$craftSite->handle}” commerce site settings"
        );

        return true;
    }

    /**
     * @param Store $store
     * @return bool
     * @throws Exception
     */
    public function deleteSiteSettings(SiteSettings $siteSettings): bool
    {
        Craft::$app->getProjectConfig()->remove(self::CONFIG_SITESETTINGS_KEY . '.' . $siteSettings->uid);

        return true;
    }

    /**
     * Handle site settings change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable
     * @throws YiiDbException
     */
    public function handleChangedSiteSettings(ConfigEvent $event): void
    {
        ProjectConfigHelper::ensureAllSitesProcessed();

        $storeSettingsUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $siteSettingsRecord = SiteSettingsRecord::findOne(['uid' => $storeSettingsUid]);

            $siteSettingsRecord->siteId = Db::idByUid(CraftTable::SITES, $storeSettingsUid);
            $siteSettingsRecord->uid = $storeSettingsUid;
            $siteSettingsRecord->storeId = Db::idByUid(Table::STORES, $data['store']);

            $siteSettingsRecord->save(false);

            $transaction->commit();
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
    public function handleDeletedSiteSettings(ConfigEvent $event): void
    {
        $storeSettingsUid = $event->tokenMatches[0];
        $siteSettingsRecord = SiteSettingsRecord::findOne(['uid' => $storeSettingsUid]);

        if (!$siteSettingsRecord->id) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            Craft::$app->getDb()->createCommand()
                ->delete(Table::SITESETTINGS, ['id' => $siteSettingsRecord->id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Refresh site settings
        //$this->refreshStores();
    }

    /**
     *
     * @param SiteEvent $event
     * @return void
     */
    public function afterSaveCraftSiteHandler(SiteEvent $event): void
    {
        $siteSettings = new SiteSettings();
        $siteSettings->siteId = $event->site->id;
        $siteSettings->storeId = $this->getPrimaryStore()->id;
        $siteSettings->uid = $event->site->uid;

        $this->saveSiteSettings($siteSettings);
    }

    public function afterDeleteCraftSiteHandler(SiteEvent $event): void
    {
    }
}
