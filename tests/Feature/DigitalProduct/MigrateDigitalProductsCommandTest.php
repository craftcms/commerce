<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Console\Commands\DigitalProducts\MigrateDigitalProductsCommand;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\DigitalProducts;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Tests\Support\DigitalProductsFixture;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/**
 * Creates the Digital Products plugin's tables, as its 5.x install and migrations leave them.
 */
function createDigitalProductsPluginTables(): void
{
    Schema::create('digitalproducts_licenses', function(Blueprint $table) {
        $table->integer('id')->primary();
        $table->integer('productId')->nullable();
        $table->integer('orderId')->nullable();
        $table->string('licenseKey');
        $table->string('ownerName')->nullable();
        $table->string('ownerEmail')->nullable();
        $table->integer('userId')->nullable();
        $table->dateTime('dateCreated');
        $table->dateTime('dateUpdated');
        $table->char('uid', 36)->default('0');
    });

    Schema::create('digitalproducts_products', function(Blueprint $table) {
        $table->integer('id')->primary();
        $table->integer('typeId');
        $table->integer('taxCategoryId');
        $table->dateTime('postDate')->nullable();
        $table->dateTime('expiryDate')->nullable();
        $table->boolean('promotable');
        $table->dateTime('dateCreated');
        $table->dateTime('dateUpdated');
        $table->char('uid', 36)->default('0');
    });

    Schema::create('digitalproducts_producttypes', function(Blueprint $table) {
        $table->integer('id', true);
        $table->integer('fieldLayoutId')->nullable();
        $table->string('name');
        $table->string('handle');
        $table->string('skuFormat')->nullable();
        $table->dateTime('dateCreated');
        $table->dateTime('dateUpdated');
        $table->char('uid', 36)->default('0');
    });

    Schema::create('digitalproducts_producttypes_sites', function(Blueprint $table) {
        $table->integer('id', true);
        $table->integer('productTypeId');
        $table->integer('siteId');
        $table->text('uriFormat')->nullable();
        $table->string('template', 500)->nullable();
        $table->boolean('hasUrls')->nullable();
        $table->dateTime('dateCreated');
        $table->dateTime('dateUpdated');
        $table->char('uid', 36)->default('0');
    });
}

/**
 * Moves Commerce's digital products data into the plugin's tables, class names and project config, so the
 * site looks like one that has only ever used the Digital Products plugin.
 *
 * @param array<string, mixed> $pluginSettings
 */
function convertToDigitalProductsPluginData(string $elementNamespace, array $pluginSettings = []): void
{
    createDigitalProductsPluginTables();

    $copy = fn(string $from, string $to, ?callable $map = null) => DB::table($from)->get()->each(
        fn(object $row) => DB::table($to)->insert($map ? $map((array)$row) : (array)$row),
    );

    $copy(Table::DIGITALPRODUCTTYPES, 'digitalproducts_producttypes');
    $copy(Table::DIGITALPRODUCTTYPES_SITES, 'digitalproducts_producttypes_sites', fn(array $row) => [
        ...collect($row)->except(['id', 'digitalProductTypeId'])->all(),
        'productTypeId' => $row['digitalProductTypeId'],
    ]);
    $copy(Table::DIGITALPRODUCTS, 'digitalproducts_products', fn(array $row) => [
        ...$row,
        'taxCategoryId' => DB::table(Table::PURCHASABLES)->where('id', $row['id'])->value('taxCategoryId'),
        'promotable' => true,
    ]);
    $copy(Table::LICENSES, 'digitalproducts_licenses');

    foreach ([Table::LICENSES, Table::DIGITALPRODUCTS, Table::DIGITALPRODUCTTYPES_SITES, Table::DIGITALPRODUCTTYPES] as $table) {
        DB::table($table)->delete();
    }

    // The plugin keeps promotable on its own table
    DB::table(Table::PURCHASABLES_STORES)->whereIn('purchasableId', DB::table('digitalproducts_products')->select('id'))->update(['promotable' => false]);

    $classes = [
        DigitalProduct::class => "$elementNamespace\\Product",
        License::class => "$elementNamespace\\License",
    ];

    foreach ($classes as $native => $plugin) {
        DB::table(CraftTable::ELEMENTS)->where('type', $native)->update(['type' => $plugin]);
    }

    $titleField = str_starts_with($elementNamespace, 'craft')
        ? 'craft\\digitalproducts\\fieldlayoutelements\\ProductTitleField'
        : 'CraftCms\\DigitalProducts\\FieldLayoutElements\\ProductTitleField';

    foreach (DB::table(CraftTable::FIELDLAYOUTS)->where('type', DigitalProduct::class)->get() as $layout) {
        DB::table(CraftTable::FIELDLAYOUTS)->where('id', $layout->id)->update([
            'type' => $classes[DigitalProduct::class],
            'config' => str_replace(json_encode(\CraftCms\Cms\FieldLayout\LayoutElements\TitleField::class), json_encode($titleField), $layout->config),
        ]);
    }

    $projectConfig = app(ProjectConfig::class);
    $projectConfig->muteEvents = true;
    $projectConfig->set('digital-products.productTypes', $projectConfig->get(DigitalProductTypes::CONFIG_KEY));
    $projectConfig->remove(DigitalProductTypes::CONFIG_KEY);
    $projectConfig->set('plugins.digital-products.settings', $pluginSettings);
    $projectConfig->muteEvents = false;

    app(DigitalProductTypes::class)->rebuildProjectConfig();
    app(DigitalProducts::class)->reset();
}

function grantPermission(User $user, string $name): void
{
    $permissionId = DB::table(CraftTable::USERPERMISSIONS)->insertGetId([
        'name' => $name,
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => (string)Str::uuid(),
    ]);

    DB::table(CraftTable::USERPERMISSIONS_USERS)->insert([
        'permissionId' => $permissionId,
        'userId' => $user->id,
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => (string)Str::uuid(),
    ]);
}

/** @return string[] */
function userPermissionNames(User $user): array
{
    return DB::table(CraftTable::USERPERMISSIONS_USERS, 'pu')
        ->join(CraftTable::USERPERMISSIONS . ' as p', 'p.id', '=', 'pu.permissionId')
        ->where('pu.userId', $user->id)
        ->pluck('p.name')
        ->map(fn(string $name) => Str::lower($name))
        ->all();
}

beforeEach(function() {
    // Plugin commands are added when Artisan starts, which happened before Commerce booted.
    app(Kernel::class)->registerCommand(app(MigrateDigitalProductsCommand::class));

    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

dataset('plugin element namespaces', [
    'Craft 5 plugin' => 'craft\\digitalproducts\\elements',
    'Craft 6 plugin' => 'CraftCms\\DigitalProducts\\Elements',
]);

test('moves the Digital Products plugin’s data into Commerce and then shows Commerce’s digital products', function(string $elementNamespace) {
    $type = DigitalProductsFixture::type();
    $product = DigitalProductsFixture::product($type, 'Field Guide', 15);
    $customer = Users::ensureUserByEmail('licensee@example.com');

    $license = new License();
    $license->productId = $product->id;
    $license->userId = $customer->id;
    expect(Elements::saveElement($license))->toBeTrue();

    $editor = Users::ensureUserByEmail('editor@example.com');
    grantPermission($editor, "digitalProducts-manageProductType:$type->uid");
    grantPermission($editor, 'digitalproducts-managelicenses');

    convertToDigitalProductsPluginData($elementNamespace, [
        'licenseKeyLength' => 12,
        'requireLoggedInUser' => false,
    ]);

    // Commerce hides its digital products while the plugin's tables have data
    expect(app(DigitalProducts::class)->isEnabled())->toBeFalse();
    get('/' . Cms::config()->cpTrigger . '/commerce/digital-products')->assertNotFound();

    artisan('commerce:digital-products:migrate', ['--force' => true])->assertSuccessful();

    expect(app(DigitalProducts::class)->isEnabled())->toBeTrue();

    foreach (DigitalProducts::PLUGIN_TABLES as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }

    $migrated = DigitalProduct::find()->id($product->id)->one();
    expect($migrated)->toBeInstanceOf(DigitalProduct::class)
        ->and($migrated->getType()->uid)->toBe($type->uid)
        ->and($migrated->getSku())->toBe('EBOOK-field-guide')
        ->and($migrated->getBasePrice())->toEqual(15.0)
        ->and($migrated->promotable)->toBeTrue()
        ->and($migrated->getFieldLayout()?->id)->toBe($type->fieldLayoutId);

    $migratedLicense = License::find()->licenseKey($license->licenseKey)->one();
    expect($migratedLicense?->id)->toBe($license->id)
        ->and($migratedLicense->userId)->toBe($customer->id)
        ->and($migratedLicense->getProduct()?->id)->toBe($product->id);

    // Field layout elements, project config, settings and permissions move too
    expect(DB::table(CraftTable::FIELDLAYOUTS)->where('id', $type->fieldLayoutId)->value('config'))->not->toContain('igitalProducts')
        ->and(app(ProjectConfig::class)->get(DigitalProductTypes::CONFIG_KEY . ".$type->uid.handle"))->toBe('ebooks')
        ->and(app(ProjectConfig::class)->get('digital-products'))->toBeNull()
        ->and(app(Plugin::class)->getSettings()->licenseKeyLength)->toBe(12)
        ->and(app(Plugin::class)->getSettings()->requireLoggedInUserForDigitalProducts)->toBeFalse()
        ->and(userPermissionNames($editor))->toEqualCanonicalizing([
            "commerce-managedigitalproducttype:$type->uid",
            'commerce-managelicenses',
        ]);

    get('/' . Cms::config()->cpTrigger . '/commerce/digital-products')->assertOk();
})->with('plugin element namespaces');

test('does nothing when there is no plugin data', function() {
    artisan('commerce:digital-products:migrate', ['--force' => true])
        ->expectsOutputToContain('There is no Digital Products plugin data to migrate.')
        ->assertSuccessful();
});

test('stops when a Commerce type already uses a plugin type’s handle', function() {
    $type = DigitalProductsFixture::type();
    DigitalProductsFixture::product($type);
    convertToDigitalProductsPluginData('craft\\digitalproducts\\elements');

    DB::table('digitalproducts_producttypes')->update(['uid' => (string)Str::uuid()]);
    DB::table(Table::DIGITALPRODUCTTYPES)->insert([
        'name' => 'Ebooks',
        'handle' => $type->handle,
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => (string)Str::uuid(),
    ]);

    artisan('commerce:digital-products:migrate', ['--force' => true])->assertFailed();

    // Nothing was moved
    expect(DB::table('digitalproducts_products')->count())->toBe(1)
        ->and(app(DigitalProducts::class)->isEnabled())->toBeFalse();
});
