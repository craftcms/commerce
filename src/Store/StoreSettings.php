<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Auth\Events\ElementAuthorizing;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Store\Data\StoreSettings as StoreSettingsModel;
use CraftCms\Commerce\Store\Models\StoreSettings as StoreSettingsRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Singleton]
class StoreSettings
{
    /**
     * @var Collection<int, StoreSettingsModel>|null
     */
    private ?Collection $allStoreSettings = null;

    /**
     * Returns the store record.
     */
    public function getStoreSettingsById(int $id): StoreSettingsModel
    {
        $store = app(Stores::class)->getStoreById($id);

        if (!$store) {
            throw new \RuntimeException('Store not found');
        }

        $storeSettings = $this->getAllStoreSettings()->firstWhere('id', $id);

        if (!$storeSettings) {
            $storeSettingsRecord = new StoreSettingsRecord();
            $storeSettingsRecord->id = $id;

            $storeSettings = new StoreSettingsModel(['id' => $storeSettingsRecord->id]);

            // Create a new blank store location
            $locationAddress = $storeSettings->getLocationAddress();
            $storeSettingsRecord->locationAddressId = $locationAddress->id;

            $storeSettingsRecord->save();

            $this->getAllStoreSettings()->put($storeSettings->id, $storeSettings);
        }

        return $storeSettings;
    }

    /**
     * @return Collection<int, StoreSettingsModel>
     */
    public function getAllStoreSettings(): Collection
    {
        if ($this->allStoreSettings === null) {
            $this->allStoreSettings = collect();
            $storeSettings = $this->query()->get();

            foreach ($storeSettings as $storeSetting) {
                $storeSetting = (array)$storeSetting;
                $this->allStoreSettings->put($storeSetting['id'], new StoreSettingsModel($storeSetting));
            }
        }

        return $this->allStoreSettings;
    }

    /**
     * Saves the store.
     */
    public function saveStoreSettings(StoreSettingsModel $storeSettings): bool
    {
        $storeSettingsRecord = StoreSettingsRecord::find($storeSettings->id);

        if (!$storeSettingsRecord) {
            throw new \RuntimeException('Invalid store ID');
        }

        $storeSettingsRecord->countries = $storeSettings->getCountries();
        $storeSettingsRecord->marketAddressCondition = $storeSettings->getMarketAddressCondition()->getConfig();

        if (!$storeSettingsRecord->save()) {
            return false;
        }

        $this->getAllStoreSettings()->put($storeSettings->id, $storeSettings);
        return true;
    }

    public function authorizeStoreLocationView(ElementAuthorizing $event): void
    {
        if (!$this->checkStoreLocationAuthorization($event)) {
            return;
        }

        // @TODO Authorize the current user against the store from $storeSettingsRecord (e.g. "commerce-manageStore:<storeUid>" permission) rather than always granting view access
        $event->authorized = true;
    }

    public function authorizeStoreLocationEdit(ElementAuthorizing $event): void
    {
        if (!$this->checkStoreLocationAuthorization($event)) {
            return;
        }

        // @TODO Authorize the current user against the store from $storeSettingsRecord (e.g. "commerce-manageStore:<storeUid>" permission) rather than always granting edit access
        $event->authorized = true;
    }

    private function checkStoreLocationAuthorization(ElementAuthorizing $event): StoreSettingsRecord|false
    {
        if (!$event->element instanceof Address) {
            return false;
        }

        $storeSettings = StoreSettingsRecord::where('locationAddressId', $event->element->getCanonicalId())->first();
        if (!$storeSettings) {
            return false;
        }

        return $storeSettings;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::STORESETTINGS)
            ->select([
                'id',
                'marketAddressCondition',
                'locationAddressId',
                'countries',
            ]);
    }
}
