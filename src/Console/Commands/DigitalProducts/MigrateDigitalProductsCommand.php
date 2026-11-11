<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\DigitalProducts;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\ElementCaches;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\ProjectConfig\Exceptions\ReadonlyException;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\DigitalProducts;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\DigitalProduct\Fields\DigitalProducts as DigitalProductsField;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Tax\TaxCategories;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;
use RuntimeException;

/**
 * Moves the Digital Products plugin’s data into Commerce.
 *
 * Elements keep their IDs, so relations, line items, drafts and revisions carry over unchanged. Types keep
 * their UIDs and field layouts, so GraphQL scopes, element index sources and field settings keep working.
 * The plugin’s tables are emptied afterward, which turns on Commerce’s own digital products features.
 */
class MigrateDigitalProductsCommand extends Command
{
    use CraftCommand;

    /**
     * Class names used by the Digital Products plugin for Craft 5 and for Craft 6.
     */
    public const array ELEMENT_TYPES = [
        'craft\\digitalproducts\\elements\\Product' => DigitalProduct::class,
        'CraftCms\\DigitalProducts\\Elements\\Product' => DigitalProduct::class,
        'craft\\digitalproducts\\elements\\License' => License::class,
        'CraftCms\\DigitalProducts\\Elements\\License' => License::class,
    ];

    public const array FIELD_TYPES = [
        'craft\\digitalproducts\\fields\\Products' => DigitalProductsField::class,
        'CraftCms\\DigitalProducts\\Fields\\Products' => DigitalProductsField::class,
    ];

    public const array FIELD_LAYOUT_ELEMENT_TYPES = [
        'craft\\digitalproducts\\fieldlayoutelements\\ProductTitleField' => TitleField::class,
        'CraftCms\\DigitalProducts\\FieldLayoutElements\\ProductTitleField' => TitleField::class,
    ];

    /**
     * Digital Products plugin settings => Commerce settings.
     */
    public const array SETTINGS = [
        'autoAssignUserOnPurchase' => 'autoAssignLicensesOnPurchase',
        'autoAssignLicensesOnUserRegistration' => 'autoAssignLicensesOnUserRegistration',
        'licenseKeyCharacters' => 'licenseKeyCharacters',
        'licenseKeyLength' => 'licenseKeyLength',
        'requireLoggedInUser' => 'requireLoggedInUserForDigitalProducts',
        'generateLicenseOnOrderPaid' => 'generateLicenseOnOrderPaid',
    ];

    #[Override]
    protected $signature = 'commerce:digital-products:migrate
        {--force : Migrate without asking for confirmation.}
    ';

    #[Override]
    protected $description = 'Moves digital products, licenses, types and settings from the Digital Products plugin into Commerce.';

    #[Override]
    protected $aliases = ['commerce/digital-products/migrate'];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<int, int> Commerce type IDs, indexed by the plugin's type IDs */
    private array $typeIdsByPluginId = [];

    public function handle(DigitalProducts $digitalProducts, ProjectConfig $projectConfig): int
    {
        $digitalProducts->reset();

        if (!$digitalProducts->hasPendingPluginData()) {
            $this->components->info('There is no Digital Products plugin data to migrate.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Move all Digital Products plugin data into Commerce? Back up your database first.', true)) {
            $this->components->warn('No data was migrated.');

            return self::SUCCESS;
        }

        try {
            $typeIds = DB::transaction(function() {
                $this->migrateFieldLayouts();
                $typeIds = $this->migrateTypes();
                $this->migrateProducts();
                $this->migrateLicenses();
                $this->retypeElementsAndFields();
                $this->migratePermissions();
                $this->emptyPluginTables();

                return $typeIds;
            });
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        Fields::invalidateCaches();
        app(ElementCaches::class)->invalidateAll();
        $digitalProducts->reset();

        $this->migrateProjectConfig($projectConfig, array_keys($typeIds));

        $this->components->twoColumnDetail('<fg=gray>Migrated</>', '<fg=gray>Count</>');
        foreach ($this->counts as $label => $count) {
            $this->components->twoColumnDetail($label, (string)$count);
        }

        $this->newLine();
        $this->components->info('Digital products data was moved into Commerce.');

        if (Plugins::isPluginInstalled(DigitalProducts::PLUGIN_HANDLE)) {
            $this->components->warn('Uninstall the Digital Products plugin now; Commerce provides its features. Change any code that references the plugin’s classes, and templates that call `craft.digitalProducts.getPlugin()`.');
        }

        return self::SUCCESS;
    }

    /**
     * Retypes the plugin's field layouts and the layout elements in them.
     */
    private function migrateFieldLayouts(): void
    {
        $layouts = DB::table(CraftTable::FIELDLAYOUTS)
            ->whereIn('type', array_keys(self::ELEMENT_TYPES))
            ->get();

        foreach ($layouts as $layout) {
            $config = $layout->config ? json_decode($layout->config, true, flags: JSON_THROW_ON_ERROR) : null;

            DB::table(CraftTable::FIELDLAYOUTS)->where('id', $layout->id)->update([
                'type' => self::ELEMENT_TYPES[$layout->type],
                'config' => $config === null ? null : json_encode(self::replaceTypes($config, self::FIELD_LAYOUT_ELEMENT_TYPES), JSON_THROW_ON_ERROR),
            ]);
        }

        $this->counts['Field layouts'] = $layouts->count();
    }

    /**
     * Copies the plugin's product types, keeping their UIDs and field layouts.
     *
     * @return array<string, int> Commerce type IDs, indexed by UID
     */
    private function migrateTypes(): array
    {
        $types = DB::table('digitalproducts_producttypes')->get();
        $typeIdsByPluginId = [];
        $typeIdsByUid = [];

        foreach ($types as $type) {
            $existing = DB::table(Table::DIGITALPRODUCTTYPES)
                ->where('uid', $type->uid)
                ->orWhere('handle', $type->handle)
                ->first();

            if ($existing && $existing->uid !== $type->uid) {
                throw new RuntimeException("A Commerce digital product type already uses the handle “{$type->handle}”. Rename one of them and run the command again.");
            }

            $id = $existing->id ?? DB::table(Table::DIGITALPRODUCTTYPES)->insertGetId([
                'fieldLayoutId' => $type->fieldLayoutId,
                'name' => $type->name,
                'handle' => $type->handle,
                'skuFormat' => $type->skuFormat,
                'dateCreated' => $type->dateCreated,
                'dateUpdated' => $type->dateUpdated,
                'uid' => $type->uid,
            ]);

            $typeIdsByPluginId[$type->id] = $id;
            $typeIdsByUid[$type->uid] = $id;
        }

        foreach (DB::table('digitalproducts_producttypes_sites')->get() as $site) {
            DB::table(Table::DIGITALPRODUCTTYPES_SITES)->updateOrInsert([
                'digitalProductTypeId' => $typeIdsByPluginId[$site->productTypeId],
                'siteId' => $site->siteId,
            ], [
                'uriFormat' => $site->uriFormat,
                'template' => $site->template,
                'hasUrls' => $site->hasUrls,
                'dateCreated' => $site->dateCreated,
                'dateUpdated' => $site->dateUpdated,
                'uid' => $site->uid,
            ]);
        }

        $this->counts['Digital product types'] = count($typeIdsByUid);
        $this->typeIdsByPluginId = $typeIdsByPluginId;

        return $typeIdsByUid;
    }

    /**
     * Copies the plugin's products. Tax category and promotable move to Commerce's purchasable tables.
     */
    private function migrateProducts(): void
    {
        $hasTaxCategory = Schema::hasColumn('digitalproducts_products', 'taxCategoryId');
        $hasPromotable = Schema::hasColumn('digitalproducts_products', 'promotable');
        $defaultTaxCategoryId = null;
        $count = 0;

        DB::table('digitalproducts_products')->orderBy('id')->chunk(500, function($products) use ($hasTaxCategory, $hasPromotable, &$defaultTaxCategoryId, &$count) {
            $existingIds = DB::table(Table::DIGITALPRODUCTS)->whereIn('id', $products->pluck('id'))->pluck('id')->all();
            $purchasableIds = DB::table(Table::PURCHASABLES)->whereIn('id', $products->pluck('id'))->pluck('id')->all();

            foreach ($products as $product) {
                if (!in_array($product->id, $existingIds)) {
                    DB::table(Table::DIGITALPRODUCTS)->insert([
                        'id' => $product->id,
                        'typeId' => $this->typeIdsByPluginId[$product->typeId],
                        'postDate' => $product->postDate,
                        'expiryDate' => $product->expiryDate,
                        'dateCreated' => $product->dateCreated,
                        'dateUpdated' => $product->dateUpdated,
                        'uid' => $product->uid,
                    ]);
                }

                $taxCategoryId = $hasTaxCategory ? $product->taxCategoryId : null;

                if (!in_array($product->id, $purchasableIds)) {
                    // Products saved before the plugin used Commerce's purchasable tables have no row yet
                    DB::table(Table::PURCHASABLES)->insert([
                        'id' => $product->id,
                        'sku' => PurchasableHelper::tempSku(),
                        'taxCategoryId' => $taxCategoryId ?? ($defaultTaxCategoryId ??= app(TaxCategories::class)->getDefaultTaxCategory()->id),
                        'dateCreated' => $product->dateCreated,
                        'dateUpdated' => $product->dateUpdated,
                        'uid' => (string)Str::uuid(),
                    ]);
                } elseif ($taxCategoryId) {
                    DB::table(Table::PURCHASABLES)->where('id', $product->id)->update(['taxCategoryId' => $taxCategoryId]);
                }

                DB::table(Table::PURCHASABLES_STORES)->where('purchasableId', $product->id)->update(array_filter([
                    'promotable' => $hasPromotable ? (bool)$product->promotable : null,
                    'freeShipping' => true,
                    'inventoryTracked' => false,
                ], fn($value) => $value !== null));

                $count++;
            }
        });

        $this->counts['Digital products'] = $count;
    }

    private function migrateLicenses(): void
    {
        $count = 0;

        DB::table('digitalproducts_licenses')->orderBy('id')->chunk(500, function($licenses) use (&$count) {
            $existingIds = DB::table(Table::LICENSES)->whereIn('id', $licenses->pluck('id'))->pluck('id')->all();

            DB::table(Table::LICENSES)->insert($licenses
                ->reject(fn(object $license) => in_array($license->id, $existingIds))
                ->map(fn(object $license) => [
                    'id' => $license->id,
                    'productId' => $license->productId,
                    'orderId' => $license->orderId,
                    'licenseKey' => $license->licenseKey,
                    'ownerName' => $license->ownerName,
                    'ownerEmail' => $license->ownerEmail,
                    'userId' => $license->userId,
                    'dateCreated' => $license->dateCreated,
                    'dateUpdated' => $license->dateUpdated,
                    'uid' => $license->uid,
                ])
                ->values()
                ->all());

            $count += $licenses->count();
        });

        $this->counts['Licenses'] = $count;
    }

    private function retypeElementsAndFields(): void
    {
        $elements = 0;

        foreach (self::ELEMENT_TYPES as $old => $new) {
            $elements += DB::table(CraftTable::ELEMENTS)->where('type', $old)->update(['type' => $new]);
        }

        $fields = 0;

        foreach (self::FIELD_TYPES as $old => $new) {
            $fields += DB::table(CraftTable::FIELDS)->where('type', $old)->update(['type' => $new]);
        }

        $this->counts['Elements retyped'] = $elements;
        $this->counts['Digital Products fields'] = $fields;
    }

    /**
     * Grants the matching Commerce permission to every user and user group that had a Digital Products permission.
     */
    private function migratePermissions(): void
    {
        $permissions = DB::table(CraftTable::USERPERMISSIONS)
            ->whereRaw('LOWER(name) LIKE ?', ['digitalproducts-%'])
            ->get();

        foreach ($permissions as $permission) {
            $newName = self::commercePermission($permission->name);

            if ($newName === null) {
                continue;
            }

            $newId = DB::table(CraftTable::USERPERMISSIONS)->whereRaw('LOWER(name) = ?', [$newName])->value('id')
                ?? DB::table(CraftTable::USERPERMISSIONS)->insertGetId([
                    'name' => $newName,
                    'dateCreated' => now(),
                    'dateUpdated' => now(),
                    'uid' => (string)Str::uuid(),
                ]);

            foreach ([CraftTable::USERPERMISSIONS_USERS => 'userId', CraftTable::USERPERMISSIONS_USERGROUPS => 'groupId'] as $table => $column) {
                $alreadyGranted = DB::table($table)->where('permissionId', $newId)->pluck($column)->all();

                DB::table($table)
                    ->where('permissionId', $permission->id)
                    ->whereNotIn($column, $alreadyGranted)
                    ->update(['permissionId' => $newId]);

                DB::table($table)->where('permissionId', $permission->id)->delete();
            }

            DB::table(CraftTable::USERPERMISSIONS)->where('id', $permission->id)->delete();
        }

        $this->counts['Permissions'] = $permissions->count();
    }

    private function emptyPluginTables(): void
    {
        foreach (DigitalProducts::PLUGIN_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    /**
     * Moves the types, the license field layout, settings, element sources and permissions in project config.
     *
     * The database already holds the migrated data, so the changes are written with events muted. Environments
     * that apply this project config must also run this command, which reuses the types it finds by UID.
     *
     * @param string[] $typeUids
     */
    private function migrateProjectConfig(ProjectConfig $projectConfig, array $typeUids): void
    {
        $typeConfigs = app(DigitalProductTypes::class)->rebuildProjectConfig();
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($typeUids as $uid) {
                $projectConfig->set(DigitalProductTypes::CONFIG_KEY . ".$uid", $typeConfigs[$uid]);
            }

            if ($licenseLayouts = $projectConfig->get('digital-products.licenseFieldLayouts')) {
                $projectConfig->set(DigitalProducts::LICENSE_FIELD_LAYOUT_CONFIG_KEY, self::replaceTypes($licenseLayouts, self::FIELD_LAYOUT_ELEMENT_TYPES));
            }

            foreach (self::ELEMENT_TYPES as $old => $new) {
                $path = ProjectConfig::PATH_ELEMENT_SOURCES . ".$old";

                if (($sources = $projectConfig->get($path)) !== null) {
                    $projectConfig->set(ProjectConfig::PATH_ELEMENT_SOURCES . ".$new", $sources);
                    $projectConfig->remove($path);
                }
            }

            foreach ($projectConfig->get('fields') ?? [] as $fieldUid => $field) {
                if (isset(self::FIELD_TYPES[$field['type'] ?? ''])) {
                    $projectConfig->set("fields.$fieldUid.type", self::FIELD_TYPES[$field['type']]);
                }
            }

            foreach ($projectConfig->get('users.groups') ?? [] as $groupUid => $group) {
                $permissions = $group['permissions'] ?? [];
                $migrated = array_values(array_unique(array_map(
                    fn(string $name) => self::commercePermission($name) ?? $name,
                    $permissions,
                )));

                if ($migrated !== $permissions) {
                    $projectConfig->set("users.groups.$groupUid.permissions", $migrated);
                }
            }

            // While the plugin is installed, removing its config would run its handlers, which delete field layouts.
            if (!Plugins::isPluginInstalled(DigitalProducts::PLUGIN_HANDLE)) {
                $projectConfig->remove('digital-products');
            }
        } catch (ReadonlyException) {
            $this->components->warn('Project config is read-only, so it wasn’t updated. Run this command where admin changes are allowed, then apply that project config here.');
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }

        $this->migrateSettings($projectConfig);
    }

    private function migrateSettings(ProjectConfig $projectConfig): void
    {
        $pluginSettings = $projectConfig->get('plugins.' . DigitalProducts::PLUGIN_HANDLE . '.settings') ?? [];
        $settings = [];

        foreach (self::SETTINGS as $old => $new) {
            if (array_key_exists($old, $pluginSettings)) {
                $settings[$new] = $pluginSettings[$old];
            }
        }

        if ($settings === []) {
            return;
        }

        try {
            if (!Plugins::savePluginSettings(app(Plugin::class), $settings)) {
                $this->components->warn('The Digital Products plugin’s settings couldn’t be copied to Commerce. Set them in Commerce’s Digital Products settings.');
            }
        } catch (ReadonlyException) {
            $this->components->warn('Project config is read-only, so the Digital Products plugin’s settings weren’t copied to Commerce.');
        }
    }

    /**
     * Returns the Commerce permission that replaces a Digital Products permission, if there is one.
     */
    public static function commercePermission(string $name): ?string
    {
        $lower = Str::lower($name);

        return match (true) {
            $lower === 'digitalproducts-manageproducts' => 'commerce-managedigitalproducts',
            $lower === 'digitalproducts-managelicenses' => 'commerce-managelicenses',
            str_starts_with($lower, 'digitalproducts-manageproducttype:') => 'commerce-managedigitalproducttype:' . substr($lower, strlen('digitalproducts-manageproducttype:')),
            default => null,
        };
    }

    /**
     * Replaces `type` values in nested config arrays, such as the elements in a field layout config.
     *
     * @param array<array-key, mixed> $config
     * @param array<string, string> $map
     * @return array<array-key, mixed>
     */
    private static function replaceTypes(array $config, array $map): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = self::replaceTypes($value, $map);
            } elseif ($key === 'type' && is_string($value) && isset($map[$value])) {
                $config[$key] = $map[$value];
            }
        }

        return $config;
    }
}
