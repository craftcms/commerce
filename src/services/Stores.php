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
use craft\commerce\models\Store;
use craft\commerce\models\Store as StoreModel;
use craft\commerce\records\Store as StoreRecord;
use craft\db\Query;
use craft\errors\SiteNotFoundException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\Site;
use Exception;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * Stores service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
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
     * @var Collection<StoreModel>|null
     */
    private ?Collection $_allStores = null;

    /**
     * @return void
     */
    public function init(): void
    {
        $this->_loadAllStores();

        parent::init();
    }

    /**
     * @return void
     */
    private function _loadAllStores(): void
    {
        if (isset($this->_allStores)) {
            return;
        }

        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.commerce.schemaVersion', true);
        if (!Craft::$app->getIsInstalled()
            || !Craft::$app->getPlugins()->isPluginInstalled('commerce')
            // @TODO remove at next major version
            || version_compare($schemaVersion, '5.0.1', '<')
        ) {
            return;
        }

        $results = $this->_createStoreQuery()->all();

        if (!empty($results)) {
            $this->_allStores = collect($results)->map(function($row) {
                $store = new Store();
                $store->setAttributes($row, false);
                return $store;
            });
        }
    }

    /**
     * @return Collection<StoreModel>
     */
    public function getAllStores(): Collection
    {
        return $this->_allStores;
    }

    /**
     * @param int $id
     * @return StoreModel|null
     */
    public function getStoreById(int $id): ?StoreModel
    {
        $stores = $this->_allStores;
        return $stores->firstWhere('id', $id);
    }

    /**
     * Returns a Query object prepped for retrieving the stores.
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

    /**
     * Saves a store.
     *
     * @param StoreModel $store The store to be saved
     * @param bool $runValidation Whether the store should be validated
     * @return bool
     */
    public function saveStore(StoreModel $store, bool $runValidation = true): bool
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
                    "Set the “{$config['name']}” store not be primary"
                );
            }
        }

        return true;
    }

    /**
     * @param int $siteId
     * @return bool
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
     * @param StoreModel $store
     * @return bool
     * @throws Exception
     */
    public function deleteStore(StoreModel $store): bool
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

        /** @var Site $site */
        $store = $this->getStoreById($storeRecord->id);

        // Did the primary site just change?
        if ($data['primary']) {
            Db::update(Table::STORES, ['primary' => false], ['not', ['id' => $store->id]]);
            Db::update(Table::STORES, ['primary' => true], ['id' => $store->id]);
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
     */
    public function refreshStores(): void
    {
        $this->_allStores = null;
        $this->_loadAllStores();
    }

    /**
     * Returns the primary store.
     */
    public function getPrimaryStore(): StoreModel
    {
        $stores = $this->_allStores;
        return $stores->firstWhere('primary', true);
    }

    /**
     * Gets an email record by uid.
     */
    private function _getStoreRecord(string $uid): StoreRecord
    {
        if ($store = StoreRecord::findOne(['uid' => $uid])) {
            return $store;
        }

        return new StoreRecord();
    }
}
