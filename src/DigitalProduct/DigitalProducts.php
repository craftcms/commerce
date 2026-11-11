<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct;

use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether Commerce’s digital products features are available, and owns the license field layout.
 *
 * Sites that used the Digital Products plugin keep their data in the plugin’s tables until
 * `commerce:digital-products:migrate` moves it into Commerce. Until then, Commerce hides its own
 * digital products features, so the plugin can keep running alongside Commerce.
 */
#[Singleton]
class DigitalProducts
{
    public const string PLUGIN_HANDLE = 'digital-products';

    public const string LICENSE_FIELD_LAYOUT_CONFIG_KEY = 'commerce.digitalProducts.licenseFieldLayouts';

    /**
     * The Digital Products plugin’s tables, in the order their rows can be deleted.
     */
    public const array PLUGIN_TABLES = [
        'digitalproducts_licenses',
        'digitalproducts_products',
        'digitalproducts_producttypes_sites',
        'digitalproducts_producttypes',
    ];

    private const string CACHE_KEY = 'commerce.digitalProducts.hasPendingPluginData';

    private ?bool $hasPendingPluginData = null;

    /**
     * Whether Commerce’s digital products features are available.
     *
     * This also decides which side registers the names Commerce shares with the Digital Products plugin: the
     * `DigitalProductInterface` and `{handle}_Product` GraphQL types, the `digitalProducts` GraphQL queries and
     * scopes, `craft.digitalProducts` and `craft:resave:digital-products`. The plugin makes the same check and
     * only registers them while this is `false`.
     */
    public function isEnabled(): bool
    {
        return !$this->hasPendingPluginData();
    }

    /**
     * Whether any of the Digital Products plugin’s tables still have rows that haven’t been migrated into Commerce.
     */
    public function hasPendingPluginData(): bool
    {
        if ($this->hasPendingPluginData !== null) {
            return $this->hasPendingPluginData;
        }

        // While the plugin is installed it can add rows at any time, so only cache the result when it isn't.
        if (Plugins::isPluginInstalled(self::PLUGIN_HANDLE)) {
            return $this->hasPendingPluginData = $this->pluginTablesHaveRows();
        }

        return $this->hasPendingPluginData = Cache::rememberForever(self::CACHE_KEY, fn() => $this->pluginTablesHaveRows());
    }

    /**
     * Clears the cached {@see hasPendingPluginData()} result, after the plugin’s data has been migrated.
     */
    public function reset(): void
    {
        $this->hasPendingPluginData = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function pluginTablesHaveRows(): bool
    {
        foreach (self::PLUGIN_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function getLicenseFieldLayout(): FieldLayout
    {
        return Fields::getLayoutByType(License::class);
    }

    public function handleChangedLicenseFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();

        if (empty($data) || empty($config = reset($data))) {
            Fields::deleteLayoutsByType(License::class);
            return;
        }

        $layout = FieldLayout::createFromConfig($config);
        $layout->id = $this->getLicenseFieldLayout()->id;
        $layout->type = License::class;
        $layout->uid = key($data);
        Fields::saveLayout($layout, false);
    }

    public function handleDeletedLicenseFieldLayout(): void
    {
        Fields::deleteLayoutsByType(License::class);
    }
}
