<?php

declare(strict_types=1);

use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Folders;
use CraftCms\Cms\Asset\Volumes;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Field\Assets as AssetsField;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Console\Commands\Licenses\MigrateDigitalProductsCommand;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\License\Elements\License;
use CraftCms\Commerce\License\Licenses;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\LicensesFixture;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

/**
 * Seeds the Digital Products plugin’s tables the way its Craft 6 version stores a product type with a download
 * field, one digital product with a file in that field, and one license.
 *
 * The digital product starts as a Commerce variant, so it has real element, content and purchasable rows, and is
 * then moved into the plugin’s tables and class names.
 */
beforeEach(function() {
    config()->set('filesystems.disks.downloads', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/downloads')]);
    Storage::fake('downloads');

    $volume = new Volume(['name' => 'Downloads', 'handle' => 'downloads', 'fsHandle' => 'downloads', 'hasUrls' => false]);
    app(Volumes::class)->saveVolume($volume) or throw new RuntimeException(json_encode($volume->errors()->all()));

    $path = tempnam(sys_get_temp_dir(), 'lic');
    file_put_contents($path, 'Field Guide PDF');
    $this->asset = new Asset([
        'volumeId' => $volume->id,
        'folderId' => app(Folders::class)->getRootFolderByVolumeId($volume->id)->id,
        'filename' => 'field-guide.pdf',
        'tempFilePath' => $path,
        'title' => 'Field Guide',
    ]);
    Elements::saveElement($this->asset) or throw new RuntimeException(json_encode($this->asset->errors()->all()));

    $this->filesField = new AssetsField(['name' => 'Files', 'handle' => 'files']);
    Fields::saveField($this->filesField) or throw new RuntimeException('Could not save field');

    $tempType = LicensesFixture::productType('temp');
    $tempType->setVariantFieldLayout(FieldLayout::createFromConfig(['type' => Variant::class, 'tabs' => [[
        'name' => 'Content',
        'elements' => [['type' => CustomField::class, 'fieldUid' => $this->filesField->uid]],
    ]]]));
    app(ProductTypes::class)->saveProductType($tempType) or throw new RuntimeException(json_encode($tempType->errors()->all()));
    $tempType = app(ProductTypes::class)->getProductTypeById($tempType->id);

    $tempProduct = LicensesFixture::product($tempType, 'Field Guide');
    $variant = LicensesFixture::variant($tempProduct, 'EBOOK-1', price: 15);
    $variant->setFieldValue('files', [$this->asset->id]);
    Elements::saveElement($variant) or throw new RuntimeException(json_encode($variant->errors()->all()));
    $this->productId = $variant->id;

    $license = new License();
    $license->variantId = $variant->id;
    $license->ownerEmail = 'customer@example.com';
    Elements::saveElement($license) or throw new RuntimeException(json_encode($license->errors()->all()));
    $this->license = $license;

    // The plugin's tables, per its Craft 6 install migration
    Schema::create('digitalproducts_producttypes', function(Blueprint $table) {
        $table->integer('id', true);
        $table->integer('fieldLayoutId')->nullable();
        $table->string('name');
        $table->string('handle');
        $table->string('skuFormat')->nullable();
        $table->char('downloadFieldUid', 36)->nullable();
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

    $now = now()->toDateTimeString();

    // The plugin's field layout keeps the variant layout's element UIDs, which the content is stored under
    $layoutConfig = $tempType->getVariantFieldLayout()->getConfig();
    array_unshift($layoutConfig['tabs'][0]['elements'], ['type' => 'CraftCms\\DigitalProducts\\FieldLayoutElements\\ProductTitleField', 'uid' => (string)Str::uuid()]);
    $this->layoutId = DB::table(CraftTable::FIELDLAYOUTS)->insertGetId([
        'type' => 'CraftCms\\DigitalProducts\\Elements\\Product',
        'config' => json_encode($layoutConfig),
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => (string)Str::uuid(),
    ]);

    $this->typeUid = (string)Str::uuid();
    $pluginTypeId = DB::table('digitalproducts_producttypes')->insertGetId([
        'fieldLayoutId' => $this->layoutId,
        'name' => 'E-books',
        'handle' => 'ebooks',
        'skuFormat' => null,
        'downloadFieldUid' => $this->filesField->uid,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => $this->typeUid,
    ]);
    DB::table('digitalproducts_producttypes_sites')->insert([
        'productTypeId' => $pluginTypeId,
        'siteId' => $variant->siteId,
        'uriFormat' => 'ebooks/{slug}',
        'template' => 'ebooks/_product',
        'hasUrls' => true,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => (string)Str::uuid(),
    ]);

    // Detach the variant from Commerce and make it a plugin product
    DB::table(CraftTable::ELEMENTS_OWNERS)->where('elementId', $variant->id)->delete();
    DB::table(Table::VARIANTS)->where('id', $variant->id)->delete();
    DB::table(CraftTable::ELEMENTS)->where('id', $tempProduct->id)->delete();
    DB::table(CraftTable::ELEMENTS)->where('id', $variant->id)->update(['type' => 'CraftCms\\DigitalProducts\\Elements\\Product']);
    DB::table(CraftTable::ELEMENTS_SITES)->where('elementId', $variant->id)->update(['title' => 'Field Guide', 'slug' => 'field-guide', 'uri' => 'ebooks/field-guide']);
    DB::table('digitalproducts_products')->insert([
        'id' => $variant->id,
        'typeId' => $pluginTypeId,
        'taxCategoryId' => DB::table(Table::PURCHASABLES)->where('id', $variant->id)->value('taxCategoryId'),
        'promotable' => true,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => (string)Str::uuid(),
    ]);

    DB::table('digitalproducts_licenses')->insert([
        ...collect((array)DB::table(Table::LICENSES)->where('id', $license->id)->first())->except('variantId')->all(),
        'productId' => $variant->id,
    ]);
    DB::table(Table::LICENSES)->delete();
    DB::table(CraftTable::ELEMENTS)->where('id', $license->id)->update(['type' => 'CraftCms\\DigitalProducts\\Elements\\License']);

    $this->editor = Users::ensureUserByEmail('editor@example.com');
    foreach (["digitalProducts-manageProductType:$this->typeUid", 'digitalProducts-manageLicenses'] as $permission) {
        $permissionId = DB::table(CraftTable::USERPERMISSIONS)->insertGetId(['name' => $permission, 'dateCreated' => $now, 'dateUpdated' => $now, 'uid' => (string)Str::uuid()]);
        DB::table(CraftTable::USERPERMISSIONS_USERS)->insert(['permissionId' => $permissionId, 'userId' => $this->editor->id, 'dateCreated' => $now, 'dateUpdated' => $now, 'uid' => (string)Str::uuid()]);
    }

    app(Licenses::class)->reset();

    // Commands register when the console starts, before the test installs Commerce
    app(Kernel::class)->registerCommand(app(MigrateDigitalProductsCommand::class));
});

test('licenses stay off until the plugin’s data is migrated', function() {
    expect(app(Licenses::class)->isEnabled())->toBeFalse();

    artisan('commerce:digital-products:migrate', ['--force' => true])->assertSuccessful();

    expect(app(Licenses::class)->isEnabled())->toBeTrue()
        ->and(DB::table('digitalproducts_products')->exists())->toBeFalse()
        ->and(DB::table('digitalproducts_licenses')->exists())->toBeFalse();

    artisan('commerce:digital-products:migrate', ['--force' => true])
        ->expectsOutputToContain('There is no Digital Products plugin data to migrate.')
        ->assertSuccessful();
});

test('digital products become variants that issue licenses, owned by products of a product type with the same UID', function() {
    artisan('commerce:digital-products:migrate', ['--force' => true])->assertSuccessful();

    $productType = app(ProductTypes::class)->getProductTypeByHandle('ebooks');

    expect($productType)->not->toBeNull()
        ->and($productType->uid)->toBe($this->typeUid)
        ->and($productType->hasLicenses)->toBeTrue()
        ->and($productType->licenseDownloadFieldUid)->toBe($this->filesField->uid)
        ->and($productType->getLicenseDownloadField()?->handle)->toBe('files')
        ->and($productType->variantFieldLayoutId)->toBe($this->layoutId);

    /** @var Variant $variant */
    $variant = Variant::find()->id($this->productId)->one();

    expect($variant)->not->toBeNull()
        ->and($variant->issuesLicenses())->toBeTrue()
        ->and($variant->getSku())->toBe('EBOOK-1')
        ->and($variant->getBasePrice())->toEqual(15.0)
        ->and($variant->getFieldValue('files')->ids())->toBe([$this->asset->id])
        ->and($variant->getOwner()->title)->toBe('Field Guide')
        ->and($variant->getOwner()->slug)->toBe('field-guide')
        ->and($variant->getOwner()->uri)->toBe('ebooks/field-guide')
        ->and($variant->getOwner()->defaultVariantId)->toBe($variant->id)
        ->and(Product::find()->type('ebooks')->ids())->toBe([$variant->getOwner()->id]);

    /** @var License $license */
    $license = License::find()->id($this->license->id)->one();

    expect($license->getVariant()?->id)->toBe($variant->id)
        ->and($license->licenseKey)->toBe($this->license->licenseKey);

    $editor = Users::getUserById($this->editor->id);

    expect($editor->can("commerce-saveProductType:$this->typeUid"))->toBeTrue()
        ->and($editor->can("commerce-deleteProductType:$this->typeUid"))->toBeTrue()
        ->and($editor->can('commerce-manageLicenses'))->toBeTrue();
});

test('a migrated license downloads from the download field on its variant', function() {
    artisan('commerce:digital-products:migrate', ['--force' => true])->assertSuccessful();
    prioritizeCommerceRoutes();

    $response = $this->get('/actions/commerce/licenses/download?' . http_build_query(['licenseKey' => $this->license->licenseKey]));

    $response->assertOk()->assertDownload('field-guide.pdf');
    expect($response->streamedContent())->toBe('Field Guide PDF');
});
