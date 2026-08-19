<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store;

use craft\commerce\Plugin;
use craft\events\ConfigEvent;
use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Order\Models\OrderStatus;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Events\DeleteStoreEvent;
use CraftCms\Commerce\Store\Events\StoreEvent;
use CraftCms\Commerce\Store\Models\SiteStore;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\Commerce\Store\Records\SiteStore as SiteStoreRecord;
use CraftCms\Commerce\Store\Records\Store as StoreRecord;
use Exception;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Singleton]
class Stores
{
    public const string EVENT_BEFORE_DELETE_STORE = 'beforeDeleteStore';

    public const string EVENT_AFTER_DELETE_STORE = 'afterDeleteStore';

    public const string EVENT_BEFORE_APPLY_STORE_DELETE = 'beforeApplyStoreDelete';

    public const string EVENT_BEFORE_SAVE_STORE = 'beforeSaveStore';

    public const string EVENT_AFTER_SAVE_STORE = 'afterSaveStore';

    /**
     * The project config path to stores data.
     */
    public const string CONFIG_STORES_KEY = 'commerce.stores';

    /**
     * The project config path to site stores data.
     */
    public const string CONFIG_SITESTORES_KEY = 'commerce.sitestores';

    /**
     * @var Collection<int, Store>|null
     */
    private ?Collection $allStores = null;

    /**
     * @var Collection<int, Store>|null
     */
    private ?Collection $allStoresBySiteId = null;

    /**
     * @var Collection<int, SiteStore>|null
     */
    private ?Collection $allSiteStores = null;

    private function loadAllStores(): void
    {
        if (isset($this->allStores)) {
            return;
        }

        $results = $this->query()->get();
        $siteStores = $this->siteStoresQuery()->select(['storeId', 'siteId'])->get();

        $allStores = [];
        $allStoresBySiteId = [];

        foreach ($results as $row) {
            $store = new Store((array)$row);

            $allStores[] = $store;

            foreach ($siteStores->where('storeId', $store->id) as $siteStore) {
                $allStoresBySiteId[$siteStore->siteId] = $store;
            }
        }

        $this->allStores = collect($allStores);
        $this->allStoresBySiteId = collect($allStoresBySiteId);
    }

    /**
     * Returns the current store.
     */
    public function getCurrentStore(): Store
    {
        return $this->getStoreBySiteId(Sites::getCurrentSite()->id) ?? $this->getPrimaryStore();
    }

    /**
     * @return Collection<int, Store>
     */
    public function getAllStores(): Collection
    {
        if ($this->allStores === null) {
            $this->loadAllStores();
        }

        return $this->allStores ?? collect();
    }

    public function getStoreById(int $id): ?Store
    {
        return $this->getAllStores()->firstWhere('id', $id);
    }

    public function getStoreByUid(string $uid): ?Store
    {
        return $this->getAllStores()->firstWhere('uid', $uid);
    }

    public function getStoreBySiteId(int $siteId): ?Store
    {
        if ($this->allStoresBySiteId === null) {
            // Population of `allStoresBySiteId` is done in `loadAllStores()`
            $this->loadAllStores();
        }

        return $this->allStoresBySiteId?->get($siteId);
    }

    public function getStoreByHandle(string $handle): ?Store
    {
        return $this->getAllStores()->firstWhere('handle', $handle);
    }

    /**
     * Returns a collections of stores that are available to a user.
     *
     * @return Collection<int, Store>
     */
    public function getStoresByUserId(int $userId): Collection
    {
        $user = Users::getUserById($userId);

        if (!$user) {
            throw new \RuntimeException('Invalid user ID: ' . $userId);
        }

        $allStores = $this->getAllStores();
        if (!Sites::isMultiSite()) {
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
     */
    public function saveStore(Store $store, bool $runValidation = true): bool
    {
        $isNewStore = !$store->id;

        // Raise 'beforeSaveStore' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getStores()->hasEventHandlers(self::EVENT_BEFORE_SAVE_STORE)) {
            $beforeEvent = new StoreEvent(
                store: $store,
                isNew: $isNewStore,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getStores()->trigger(self::EVENT_BEFORE_SAVE_STORE, $beforeEvent);
        }

        if ($runValidation && !$store->validate()) {
            Log::info('Store not saved due to validation error.');
            return false;
        }

        if ($isNewStore) {
            $store->uid = Str::uuid()->toString();
        } elseif (!$store->uid) {
            $store->uid = CraftDb::uidById(Table::STORES, $store->id);
        }

        $configPath = self::CONFIG_STORES_KEY . '.' . $store->uid;
        ProjectConfig::set(
            $configPath,
            $store->getConfig(),
            "Save the \"{$store->handle}\" store"
        );

        // Now that we have a store ID, save it on the model
        if ($isNewStore) {
            $store->id = CraftDb::idByUid(Table::STORES, $store->uid);

            // Create any default data we need for the store
            $orderStatus = new OrderStatus([
                'name' => 'New',
                'handle' => 'new',
                'color' => 'green',
                'default' => true,
                'storeId' => $store->id,
            ]);
            app(OrderStatuses::class)->saveOrderStatus($orderStatus);
        }

        // Update the other primary store.
        if ($store->primary) {
            foreach (ProjectConfig::get(self::CONFIG_STORES_KEY) as $uid => $config) {
                if ($uid !== $store->uid && isset($config['primary']) && $config['primary'] === true) {
                    $configPath = self::CONFIG_STORES_KEY . '.' . $uid;
                    $config['primary'] = false; // Set the other to false
                    ProjectConfig::set(
                        $configPath,
                        $config,
                        "Set the \"{$config['name']}\" store to not be primary"
                    );
                }
            }
        }

        $this->refreshStores();

        return true;
    }

    /**
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
     * @throws Exception
     */
    public function deleteStore(Store $store): bool
    {
        // Make sure this isn't the primary site
        if ($store->id === $this->getPrimaryStore()?->id) {
            throw new Exception('You cannot delete the primary store.');
        }

        // Raise 'beforeDeleteStore' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getStores()->hasEventHandlers(self::EVENT_BEFORE_DELETE_STORE)) {
            $event = new DeleteStoreEvent(
                store: $store,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getStores()->trigger(self::EVENT_BEFORE_DELETE_STORE, $event);
        }

        $path = self::CONFIG_STORES_KEY . '.' . $store->uid;
        ProjectConfig::remove($path, "Delete the \"{$store->handle}\" store");

        return true;
    }

    /**
     * Handle store status change.
     *
     * @throws Throwable
     */
    public function handleChangedStore(ConfigEvent $event): void
    {
        $storeUid = $event->tokenMatches[0];
        $data = $event->newValue;

        DB::beginTransaction();
        try {
            $storeRecord = $this->getStoreRecord($storeUid);
            $isNewStore = !$storeRecord->exists;

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

            $storeRecord->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Did the primary site just change?
        if ($data['primary']) {
            DB::table(Table::STORES)->where('id', '!=', $storeRecord->id)->update(['primary' => false]);
            DB::table(Table::STORES)->where('id', $storeRecord->id)->update(['primary' => true]);
        }

        $paymentCurrency = app(PaymentCurrencies::class)->getPaymentCurrencyByIso($data['currency'] ?? '', $storeRecord->id);
        if (!$paymentCurrency) {
            $now = now()->toDateTimeString();
            DB::table(Table::PAYMENTCURRENCIES)->insert([
                'iso' => $data['currency'] ?? 'USD',
                'storeId' => $storeRecord->id,
                'rate' => 1,
                'dateCreated' => $now,
                'dateUpdated' => $now,
            ]);
        }

        if (app(ShippingCategories::class)->getAllShippingCategories($storeRecord->id)->isEmpty()) {
            $now = now()->toDateTimeString();
            DB::table(Table::SHIPPINGCATEGORIES)->insert([
                'name' => 'General',
                'handle' => 'general',
                'default' => true,
                'storeId' => $storeRecord->id,
                'dateCreated' => $now,
                'dateUpdated' => $now,
            ]);
        }

        $this->refreshStores();

        // Raise 'afterSaveStore' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getStores()->hasEventHandlers(self::EVENT_AFTER_SAVE_STORE)) {
            $afterEvent = new StoreEvent(
                store: $this->getStoreById($storeRecord->id),
                isNew: $isNewStore,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getStores()->trigger(self::EVENT_AFTER_SAVE_STORE, $afterEvent);
        }
    }

    /**
     * Handle a deleted Store.
     *
     * @throws Throwable
     */
    public function handleDeletedStore(ConfigEvent $event): void
    {
        $storeUid = $event->tokenMatches[0];
        $storeRecord = $this->getStoreRecord($storeUid);

        if (!$storeRecord->id) {
            return;
        }

        /** @var Store $store */
        $store = $this->getStoreById($storeRecord->id);

        // Raise 'beforeApplyStoreDelete' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getStores()->hasEventHandlers(self::EVENT_BEFORE_APPLY_STORE_DELETE)) {
            $blockerEvent = new DeleteStoreEvent(
                store: $store,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getStores()->trigger(self::EVENT_BEFORE_APPLY_STORE_DELETE, $blockerEvent);
        }

        DB::beginTransaction();

        try {
            $locationAddressId = $store->getSettings()->getLocationAddressId();

            DB::table(Table::STORES)->where('id', $storeRecord->id)->delete();

            // Delete store address
            if ($locationAddressId) {
                Elements::deleteElementById($locationAddressId, Address::class, hardDelete: true);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
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

        // Raise 'afterDeleteStore' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getStores()->hasEventHandlers(self::EVENT_AFTER_DELETE_STORE)) {
            $afterEvent = new DeleteStoreEvent(
                store: $store,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getStores()->trigger(self::EVENT_AFTER_DELETE_STORE, $afterEvent);
        }
    }

    /**
     * Refresh the status of all stores based on the DB data.
     */
    public function refreshStores(): void
    {
        $this->allStores = null;
        $this->allStoresBySiteId = null;
        $this->loadAllStores();
    }

    /**
     * Returns the primary store.
     */
    public function getPrimaryStore(): ?Store
    {
        return $this->getAllStores()->firstWhere('primary', true);
    }

    /**
     * @param int[] $ids
     */
    public function reorderStores(array $ids): bool
    {
        $uidsByIds = CraftDb::uidsByIds(Table::STORES, $ids);

        foreach ($ids as $sortOrder => $id) {
            if (!empty($uidsByIds[$id])) {
                $uid = $uidsByIds[$id];
                ProjectConfig::set(self::CONFIG_STORES_KEY . '.' . $uid . '.sortOrder', $sortOrder + 1);
            }
        }

        $this->refreshStores();

        return true;
    }

    /**
     * Gets a store record by uid.
     */
    private function getStoreRecord(string $uid): StoreRecord
    {
        if ($store = StoreRecord::where('uid', $uid)->first()) {
            return $store;
        }

        return new StoreRecord();
    }

    private function query(): Builder
    {
        $selectColumns = [
            'handle',
            'id',
            'name',
            'primary',
            'uid',
        ];

        // TODO: Remove this schemaVersion guard in Commerce 6.0 once all installs are past schema 5.0.72 and the store settings columns are guaranteed to exist
        // Note: right after a fresh install (same request), Plugins::installPlugin() only caches
        // ['id', 'enabled'] — no 'schemaVersion' yet — so a missing key means "freshly installed",
        // which always has the settings columns, not "pre-5.0.72".
        $commerce = Plugins::getStoredPluginInfo('commerce');
        $hasSettingsColumns = !isset($commerce['schemaVersion']) || version_compare((string)$commerce['schemaVersion'], '5.0.72', '>=');

        if ($hasSettingsColumns) {
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

        $query = DB::table(Table::STORES)->select($selectColumns);

        if ($hasSettingsColumns) {
            $query->orderBy('sortOrder');
        }

        return $query;
    }

    /**
     * @return Collection<int, Site>
     */
    public function getAllSitesForStore(Store $store): Collection
    {
        $sites = Sites::getAllSites();

        return $this->getAllSiteStores()
            ->filter(fn(SiteStore $siteStore) => $siteStore->storeId == $store->id)
            ->map(fn(SiteStore $siteStore) => collect($sites)->firstWhere('id', $siteStore->siteId));
    }

    /**
     * @return Collection<int, SiteStore>
     */
    public function getAllSiteStores(): Collection
    {
        if ($this->allSiteStores !== null) {
            return $this->allSiteStores;
        }

        $siteStores = [];
        foreach ($this->siteStoresQuery()->get() as $store) {
            $siteStores[] = new SiteStore((array)$store);
        }

        return !empty($siteStores) ? $this->allSiteStores = collect($siteStores) : collect();
    }

    /**
     * Returns sites that are assigned to more than one store assigned, so that other new stores can use them.
     */
    public function getSiteIdsAvailableForAssignmentToNewStores(): array
    {
        // Sites that are assigned to more than one store
        $storeIds = DB::table(Table::SITESTORES)
            ->select('storeId')
            ->groupBy('storeId')
            ->havingRaw('COUNT(storeId) > 1')
            ->pluck('storeId');

        return DB::table(Table::SITESTORES)
            ->select('siteId')
            ->whereIn('storeId', $storeIds)
            ->groupBy('siteId')
            ->pluck('siteId')
            ->all();
    }

    /**
     * @throws Throwable
     */
    public function saveSiteStore(SiteStore $siteStore, bool $runValidation = true): bool
    {
        if ($runValidation && !$siteStore->validate()) {
            Log::info('Site store mapping not saved due to validation error.');
            return false;
        }

        // We use the same UID as the site since we only have one record per site.
        // This also makes it easier to see what site a store is mapped to in the project config.
        $craftSite = Sites::getSiteById($siteStore->siteId);
        if (!$craftSite) {
            throw new \RuntimeException('Invalid site ID: ' . $siteStore->siteId);
        }

        if (!$siteStore->uid) {
            $siteStore->uid = CraftDb::uidById(CraftTable::SITES, $siteStore->siteId);
        }

        $configPath = self::CONFIG_SITESTORES_KEY . '.' . $siteStore->uid;
        ProjectConfig::set(
            $configPath,
            $siteStore->getConfig(),
            "Save the \"{$craftSite->handle}\" commerce site store mapping"
        );

        $this->refreshStores();

        return true;
    }

    /**
     * Handle site store mapping change.
     *
     * @throws Throwable
     */
    public function handleChangedSiteStore(ConfigEvent $event): void
    {
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigData::ensureAllStoresProcessed();

        $siteStoreUid = $event->tokenMatches[0];
        $data = $event->newValue;

        DB::beginTransaction();
        try {
            $siteStoreRecord = SiteStoreRecord::where('uid', $siteStoreUid)->first();

            if (!$siteStoreRecord) {
                $siteStoreRecord = new SiteStoreRecord();
            }

            $siteStoreRecord->siteId = CraftDb::idByUid(CraftTable::SITES, $siteStoreUid);
            $siteStoreRecord->storeId = CraftDb::idByUid(Table::STORES, $data['store']);
            $siteStoreRecord->uid = $siteStoreUid;

            $siteStoreRecord->save();

            DB::commit();

            $this->refreshStores();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle a deleted Store.
     *
     * @throws Throwable
     */
    public function handleDeletedSiteStore(ConfigEvent $event): void
    {
        $storeStoreUid = $event->tokenMatches[0];
        $siteStoreRecord = SiteStoreRecord::where('uid', $storeStoreUid)->first(); // site_stores uses the site UID

        if (!$siteStoreRecord) {
            return;
        }

        DB::beginTransaction();

        try {
            DB::table(Table::SITESTORES)->where('siteId', $siteStoreRecord->siteId)->delete();

            DB::commit();

            $this->refreshStores();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public function afterSaveCraftSiteHandler(SiteSaved $event): void
    {
        $siteStore = SiteStoreRecord::find($event->site->id);

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
     * @throws Throwable
     */
    public function afterDeleteCraftSiteHandler(SiteDeleted $event): void
    {
        $siteStores = $this->getAllSiteStores();
        $siteStore = $siteStores->firstWhere('siteId', $event->site->id);

        if (!$siteStore) {
            return;
        }

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
        ProjectConfig::remove(self::CONFIG_SITESTORES_KEY . '.' . $siteStore->uid);
    }

    private function siteStoresQuery(): Builder
    {
        return DB::table(Table::SITESTORES)
            ->select([
                'siteId',
                'storeId',
                'uid',
            ]);
    }
}
