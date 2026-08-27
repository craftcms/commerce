<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Store\Data\StoreSettings as StoreSettingsModel;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Store\StoreSettings::class)` instead.
 */
class StoreSettings extends Component
{
    public function getStoreSettingsById(int $id): StoreSettingsModel
    {
        return app(\CraftCms\Commerce\Store\StoreSettings::class)->getStoreSettingsById($id);
    }

    /**
     * @return Collection<int, StoreSettingsModel>
     */
    public function getAllStoreSettings(): Collection
    {
        return app(\CraftCms\Commerce\Store\StoreSettings::class)->getAllStoreSettings();
    }

    /**
     * @throws InvalidConfigException
     */
    public function saveStoreSettings(StoreSettingsModel $storeSettings): bool
    {
        return app(\CraftCms\Commerce\Store\StoreSettings::class)->saveStoreSettings($storeSettings);
    }
}
