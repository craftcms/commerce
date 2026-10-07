<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Store\Data\StoreSettings getStoreSettingsById(int $id)
 * @method static \Illuminate\Support\Collection getAllStoreSettings()
 * @method static bool saveStoreSettings(\CraftCms\Commerce\Store\Data\StoreSettings $storeSettings)
 * @method static void authorizeStoreLocationView(\CraftCms\Cms\Auth\Events\ElementAuthorizing $event)
 * @method static void authorizeStoreLocationEdit(\CraftCms\Cms\Auth\Events\ElementAuthorizing $event)
 *
 * @see \CraftCms\Commerce\Store\StoreSettings
 */
class StoreSettings extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Store\StoreSettings::class;
    }
}
