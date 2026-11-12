<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Licenses;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\ElementCaches;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Exceptions\ReadonlyException;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\License\Elements\License;
use CraftCms\Commerce\License\Licenses;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Fields\Variants as VariantsField;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantTitleField;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;
use RuntimeException;

/**
 * Moves the Digital Products plugin’s data into Commerce.
 *
 * Each digital product type becomes a product type that allows licenses, with the same UID, so permissions,
 * GraphQL scopes and relation field sources keep working. Each digital product becomes a variant that issues
 * licenses and keeps its element ID, so line items, relations and licenses keep pointing at it, owned by a new
 * product with the digital product’s title, slug, URI and dates. Licenses keep their IDs and keys.
 * The plugin’s tables are emptied afterward, which turns on Commerce’s license features.
 */
class MigrateDigitalProductsCommand extends Command
{
    use CraftCommand;

    /** The Digital Products plugin’s product and license classes, for Craft 5 and Craft 6. */
    public const array PRODUCT_TYPES = [
        'craft\\digitalproducts\\elements\\Product',
        'CraftCms\\DigitalProducts\\Elements\\Product',
    ];

    public const array LICENSE_TYPES = [
        'craft\\digitalproducts\\elements\\License',
        'CraftCms\\DigitalProducts\\Elements\\License',
    ];

    public const array FIELD_TYPES = [
        'craft\\digitalproducts\\fields\\Products',
        'CraftCms\\DigitalProducts\\Fields\\Products',
    ];

    public const array TITLE_FIELD_TYPES = [
        'craft\\digitalproducts\\fieldlayoutelements\\ProductTitleField',
        'CraftCms\\DigitalProducts\\FieldLayoutElements\\ProductTitleField',
    ];

    /** Digital Products plugin settings => Commerce settings. */
    public const array SETTINGS = [
        'autoAssignUserOnPurchase' => 'autoAssignLicensesOnPurchase',
        'autoAssignLicensesOnUserRegistration' => 'autoAssignLicensesOnUserRegistration',
        'licenseKeyCharacters' => 'licenseKeyCharacters',
        'licenseKeyLength' => 'licenseKeyLength',
        'requireLoggedInUser' => 'requireLoggedInUserForLicenses',
        'generateLicenseOnOrderPaid' => 'generateLicenseOnOrderPaid',
    ];

    #[Override]
    protected $signature = 'commerce:digital-products:migrate
        {--force : Migrate without asking for confirmation.}
    ';

    #[Override]
    protected $description = 'Moves digital products, licenses, product types and settings from the Digital Products plugin into Commerce products, variants and licenses.';

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(Licenses $licenses, ProjectConfig $projectConfig): int
    {
        $licenses->reset();

        if (!$licenses->hasPendingPluginData()) {
            $this->components->info('There is no Digital Products plugin data to migrate.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Move all Digital Products plugin data into Commerce? Back up your database first.', true)) {
            $this->components->warn('No data was migrated.');

            return self::SUCCESS;
        }

        try {
            // Product types are saved through project config, which can't be part of the transaction
            $productTypes = $this->migrateProductTypes();

            DB::transaction(function() use ($productTypes) {
                $this->migrateProducts($productTypes);
                $this->migrateLicenses();
                $this->migrateFields();
                $this->migratePermissions();
                $this->emptyPluginTables();
            });
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        Fields::invalidateCaches();
        app(ElementCaches::class)->invalidateAll();
        $licenses->reset();

        $this->migrateProjectConfig($projectConfig);

        $this->components->twoColumnDetail('<fg=gray>Migrated</>', '<fg=gray>Count</>');
        foreach ($this->counts as $label => $count) {
            $this->components->twoColumnDetail($label, (string)$count);
        }

        $this->newLine();
        $this->components->info('Digital products were moved into Commerce products, variants and licenses.');

        if (Plugins::isPluginInstalled(Licenses::PLUGIN_HANDLE)) {
            $this->components->warn('Uninstall the Digital Products plugin now; Commerce provides its features. Update templates that use `craft.digitalProducts`, which Commerce doesn’t provide.');
        }

        return self::SUCCESS;
    }

    /**
     * Creates a product type that allows licenses for each digital product type, keeping its UID. The digital
     * product type’s field layout becomes the variant field layout, keeping its layout element UIDs, which
     * custom field values are stored under.
     *
     * @return array<int, ProductType> Commerce product types, indexed by the plugin’s type IDs
     */
    private function migrateProductTypes(): array
    {
        $productTypes = [];
        $hasDownloadField = Schema::hasColumn('digitalproducts_producttypes', 'downloadFieldUid');

        foreach (DB::table('digitalproducts_producttypes')->get() as $pluginType) {
            $existing = DB::table(Table::PRODUCTTYPES)
                ->where('uid', $pluginType->uid)
                ->orWhere('handle', $pluginType->handle)
                ->first();

            if ($existing && $existing->uid !== $pluginType->uid) {
                throw new RuntimeException("A product type already uses the handle “{$pluginType->handle}”. Rename one of them and run the command again.");
            }

            if ($existing) {
                // Created by an earlier run, or by applying project config from an environment that ran this command
                $productTypes[$pluginType->id] = app(ProductTypes::class)->getProductTypeById($existing->id);
                continue;
            }

            $productType = new ProductType();
            $productType->uid = $pluginType->uid;
            $productType->name = $pluginType->name;
            $productType->handle = $pluginType->handle;
            $productType->skuFormat = $pluginType->skuFormat;
            $productType->hasLicenses = true;
            $productType->licenseDownloadFieldUid = $hasDownloadField ? $pluginType->downloadFieldUid : null;
            $productType->variantTitleFormat = '{product.title}';
            $productType->hasVariantTitleField = false;

            $siteSettings = [];
            foreach (DB::table('digitalproducts_producttypes_sites')->where('productTypeId', $pluginType->id)->get() as $site) {
                $siteSettings[$site->siteId] = new ProductTypeSite([
                    'siteId' => $site->siteId,
                    'hasUrls' => (bool)$site->hasUrls,
                    'uriFormat' => $site->uriFormat,
                    'template' => $site->template,
                ]);
            }
            $productType->setSiteSettings($siteSettings);

            if ($pluginType->fieldLayoutId && ($layoutRow = DB::table(CraftTable::FIELDLAYOUTS)->where('id', $pluginType->fieldLayoutId)->first())) {
                $config = $layoutRow->config ? json_decode($layoutRow->config, true, flags: JSON_THROW_ON_ERROR) : [];
                $config = self::removeTitleFields($config);

                $variantLayout = FieldLayout::createFromConfig([...$config, 'type' => Variant::class]);
                $variantLayout->id = (int)$layoutRow->id;
                $variantLayout->uid = $layoutRow->uid;

                // Retype the plugin's layout row, which the product type's project config handler then updates in place
                DB::table(CraftTable::FIELDLAYOUTS)->where('id', $layoutRow->id)->update(['type' => Variant::class]);

                $productType->setVariantFieldLayout($variantLayout);
            }

            app(ProjectConfig::class)->set(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.' . $productType->uid, $productType->getConfig());

            $saved = app(ProductTypes::class)->getProductTypeByHandle($productType->handle)
                ?? throw new RuntimeException("The “{$pluginType->handle}” product type couldn’t be created.");

            $productTypes[$pluginType->id] = $saved;
        }

        $this->counts['Product types'] = count($productTypes);

        return $productTypes;
    }

    /**
     * Turns each digital product into a variant that issues licenses, owned by a new product.
     *
     * @param array<int, ProductType> $productTypes
     */
    private function migrateProducts(array $productTypes): void
    {
        $hasPromotable = Schema::hasColumn('digitalproducts_products', 'promotable');
        $hasTaxCategory = Schema::hasColumn('digitalproducts_products', 'taxCategoryId');
        $count = 0;

        foreach (DB::table('digitalproducts_products')->orderBy('id')->get() as $pluginProduct) {
            if (DB::table(Table::VARIANTS)->where('id', $pluginProduct->id)->exists()) {
                continue;
            }

            $element = DB::table(CraftTable::ELEMENTS)->where('id', $pluginProduct->id)->first();

            // Drafts and revisions of digital products have no variant equivalent
            if (!$element || $element->canonicalId !== null || $element->draftId !== null || $element->revisionId !== null) {
                continue;
            }

            $productType = $productTypes[$pluginProduct->typeId];
            $sites = DB::table(CraftTable::ELEMENTS_SITES)->where('elementId', $pluginProduct->id)->get()->keyBy('siteId');
            $primarySite = $sites->first(fn(object $site) => isset($productType->getSiteSettings()[$site->siteId])) ?? $sites->first();

            $product = new Product();
            $product->typeId = $productType->id;
            $product->siteId = $primarySite->siteId;
            $product->title = $primarySite->title;
            $product->slug = $primarySite->slug;
            $product->enabled = (bool)$element->enabled;
            $product->postDate = $pluginProduct->postDate ? new DateTime($pluginProduct->postDate) : null;
            $product->expiryDate = $pluginProduct->expiryDate ? new DateTime($pluginProduct->expiryDate) : null;
            $product->dateCreated = new DateTime($pluginProduct->dateCreated);

            // A product needs a variant to pass validation; its variant is attached below
            if (!Elements::saveElement($product, runValidation: false)) {
                throw new RuntimeException("Digital product {$pluginProduct->id} couldn’t be moved to a product.");
            }

            // Carry each site's title, slug, URI and status over to the product. Variants don't have URIs.
            foreach ($sites as $siteId => $site) {
                DB::table(CraftTable::ELEMENTS_SITES)
                    ->where('elementId', $product->id)
                    ->where('siteId', $siteId)
                    ->update(['title' => $site->title, 'slug' => $site->slug, 'uri' => $site->uri, 'enabled' => $site->enabled]);
            }

            DB::table(CraftTable::ELEMENTS_SITES)->where('elementId', $pluginProduct->id)->update(['uri' => null]);
            DB::table(CraftTable::ELEMENTS)->where('id', $pluginProduct->id)->update(['type' => Variant::class, 'enabled' => true]);

            DB::table(Table::VARIANTS)->insert([
                'id' => $pluginProduct->id,
                'primaryOwnerId' => $product->id,
                'isDefault' => true,
                'issuesLicense' => true,
                'dateCreated' => $pluginProduct->dateCreated,
                'dateUpdated' => $pluginProduct->dateUpdated,
                'uid' => $pluginProduct->uid,
            ]);

            DB::table(CraftTable::ELEMENTS_OWNERS)->insert([
                'elementId' => $pluginProduct->id,
                'ownerId' => $product->id,
                'sortOrder' => 1,
            ]);

            DB::table(Table::PURCHASABLES_STORES)->where('purchasableId', $pluginProduct->id)->update(array_filter([
                'promotable' => $hasPromotable ? (bool)$pluginProduct->promotable : null,
                'freeShipping' => true,
                'inventoryTracked' => false,
            ], fn($value) => $value !== null));

            if ($hasTaxCategory && $pluginProduct->taxCategoryId) {
                DB::table(Table::PURCHASABLES)->where('id', $pluginProduct->id)->update(['taxCategoryId' => $pluginProduct->taxCategoryId]);
            }

            DB::table(Table::PRODUCTS)->where('id', $product->id)->update([
                'defaultVariantId' => $pluginProduct->id,
                'defaultSku' => DB::table(Table::PURCHASABLES)->where('id', $pluginProduct->id)->value('sku'),
            ]);

            $count++;
        }

        $this->counts['Digital products moved to variants'] = $count;
    }

    private function migrateLicenses(): void
    {
        $count = 0;

        DB::table('digitalproducts_licenses')->orderBy('id')->chunk(500, function($pluginLicenses) use (&$count) {
            $existingIds = DB::table(Table::LICENSES)->whereIn('id', $pluginLicenses->pluck('id'))->pluck('id')->all();
            $variantIds = DB::table(Table::VARIANTS)->whereIn('id', $pluginLicenses->pluck('productId')->filter())->pluck('id')->all();

            DB::table(Table::LICENSES)->insert($pluginLicenses
                ->reject(fn(object $license) => in_array($license->id, $existingIds))
                ->map(fn(object $license) => [
                    'id' => $license->id,
                    'variantId' => in_array($license->productId, $variantIds) ? $license->productId : null,
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

            $count += $pluginLicenses->count();
        });

        DB::table(CraftTable::ELEMENTS)->whereIn('type', self::LICENSE_TYPES)->update(['type' => License::class]);
        DB::table(CraftTable::FIELDLAYOUTS)->whereIn('type', self::LICENSE_TYPES)->update(['type' => License::class]);

        $this->counts['Licenses'] = $count;
    }

    /**
     * Turns “Digital Products” relation fields into Variants fields. Their sources are product type keys, which
     * keep working because the product types keep the digital product types’ UIDs.
     */
    private function migrateFields(): void
    {
        $this->counts['Digital Products fields'] = DB::table(CraftTable::FIELDS)
            ->whereIn('type', self::FIELD_TYPES)
            ->update(['type' => VariantsField::class]);
    }

    /**
     * Grants the matching Commerce permissions to every user and user group that had a Digital Products permission.
     */
    private function migratePermissions(): void
    {
        $permissions = DB::table(CraftTable::USERPERMISSIONS)
            ->whereRaw('LOWER(name) LIKE ?', ['digitalproducts-%'])
            ->get();

        foreach ($permissions as $permission) {
            foreach (self::commercePermissions($permission->name) as $newName) {
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
                        ->get()
                        ->each(fn(object $row) => DB::table($table)->insert([
                            'permissionId' => $newId,
                            $column => $row->$column,
                            'dateCreated' => now(),
                            'dateUpdated' => now(),
                            'uid' => (string)Str::uuid(),
                        ]));
                }
            }

            DB::table(CraftTable::USERPERMISSIONS)->where('id', $permission->id)->delete();
        }

        $this->counts['Permissions'] = $permissions->count();
    }

    private function emptyPluginTables(): void
    {
        foreach (Licenses::PLUGIN_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    /**
     * Moves the license field layout, element sources, field types and group permissions in project config, and
     * copies the plugin’s settings.
     */
    private function migrateProjectConfig(ProjectConfig $projectConfig): void
    {
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            if ($licenseLayouts = $projectConfig->get('digital-products.licenseFieldLayouts')) {
                $projectConfig->set(Licenses::FIELD_LAYOUT_CONFIG_KEY, $licenseLayouts);
            }

            foreach (self::LICENSE_TYPES as $old) {
                $path = ProjectConfig::PATH_ELEMENT_SOURCES . ".$old";

                if (($sources = $projectConfig->get($path)) !== null) {
                    $projectConfig->set(ProjectConfig::PATH_ELEMENT_SOURCES . '.' . License::class, $sources);
                    $projectConfig->remove($path);
                }
            }

            foreach (self::PRODUCT_TYPES as $old) {
                $projectConfig->remove(ProjectConfig::PATH_ELEMENT_SOURCES . ".$old");
            }

            foreach ($projectConfig->get('fields') ?? [] as $fieldUid => $field) {
                if (in_array($field['type'] ?? '', self::FIELD_TYPES, true)) {
                    $projectConfig->set("fields.$fieldUid.type", VariantsField::class);
                }
            }

            foreach ($projectConfig->get('users.groups') ?? [] as $groupUid => $group) {
                $permissions = $group['permissions'] ?? [];
                $migrated = array_values(array_unique(array_merge(...array_map(
                    fn(string $name) => str_starts_with(strtolower($name), 'digitalproducts-') ? self::commercePermissions($name) : [$name],
                    $permissions,
                ) ?: [[]])));

                if ($migrated !== $permissions) {
                    $projectConfig->set("users.groups.$groupUid.permissions", $migrated);
                }
            }

            // While the plugin is installed, removing its config would run its handlers, which delete its products.
            if (!Plugins::isPluginInstalled(Licenses::PLUGIN_HANDLE)) {
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
        $pluginSettings = $projectConfig->get('plugins.' . Licenses::PLUGIN_HANDLE . '.settings') ?? [];
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
                $this->components->warn('The Digital Products plugin’s settings couldn’t be copied to Commerce. Set them in Commerce’s Licenses settings.');
            }
        } catch (ReadonlyException) {
            $this->components->warn('Project config is read-only, so the Digital Products plugin’s settings weren’t copied to Commerce.');
        }
    }

    /**
     * Returns the Commerce permissions that replace a Digital Products permission. Managing a digital product type
     * becomes viewing, creating, saving and deleting products of the product type with the same UID.
     *
     * @return string[]
     */
    public static function commercePermissions(string $name): array
    {
        $lower = Str::lower($name);

        if ($lower === 'digitalproducts-managelicenses') {
            return ['commerce-managelicenses'];
        }

        if (str_starts_with($lower, 'digitalproducts-manageproducttype:')) {
            $uid = substr($lower, strlen('digitalproducts-manageproducttype:'));

            return array_map(fn(string $action) => "commerce-{$action}producttype:$uid", ['view', 'create', 'save', 'delete']);
        }

        return [];
    }

    /**
     * Removes the plugin's title field from a field layout config; the variant title comes from the product.
     *
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     */
    private static function removeTitleFields(array $config): array
    {
        foreach ($config['tabs'] ?? [] as $i => $tab) {
            $config['tabs'][$i]['elements'] = array_values(array_filter(
                $tab['elements'] ?? [],
                fn(array $element) => !in_array($element['type'] ?? '', [...self::TITLE_FIELD_TYPES, VariantTitleField::class], true),
            ));
        }

        return $config;
    }
}
