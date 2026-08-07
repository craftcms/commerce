<?php

namespace craft\commerce\services;

use craft\events\AuthorizationCheckEvent;
use CraftCms\Commerce\Store\Models\StoreSettings as StoreSettingsModel;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\StoreSettings::class)` instead.
 */
class StoreSettings extends Component
{
    public function getStoreSettingsById(int $id): StoreSettingsModel
    {
        return app(\CraftCms\Commerce\Services\StoreSettings::class)->getStoreSettingsById($id);
    }

    /**
     * @return Collection<int, StoreSettingsModel>
     */
    public function getAllStoreSettings(): Collection
    {
        return app(\CraftCms\Commerce\Services\StoreSettings::class)->getAllStoreSettings();
    }

    /**
     * @throws InvalidConfigException
     */
    public function saveStoreSettings(StoreSettingsModel $storeSettings): bool
    {
        return app(\CraftCms\Commerce\Services\StoreSettings::class)->saveStoreSettings($storeSettings);
    }

    public function authorizeStoreLocationView(AuthorizationCheckEvent $event): void
    {
        app(\CraftCms\Commerce\Services\StoreSettings::class)->authorizeStoreLocationView($event);
    }

    public function authorizeStoreLocationEdit(AuthorizationCheckEvent $event): void
    {
        app(\CraftCms\Commerce\Services\StoreSettings::class)->authorizeStoreLocationEdit($event);
    }
}
