<?php

declare(strict_types=1);

use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Folders;
use CraftCms\Cms\Asset\Volumes;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Field\Assets as AssetsField;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Twig\TwigRenderer;
use CraftCms\Commerce\License\Elements\License;
use CraftCms\Commerce\License\Licenses;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\FieldLayoutElements\VariantIssuesLicenseField;
use CraftCms\Commerce\Tests\Support\LicensesFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

function completeOrderFor(string $email, array $lines): Order
{
    $order = new Order();
    $order->setCustomer(Users::ensureUserByEmail($email));
    $order->setLineItems(array_map(
        fn(array $line) => app(LineItems::class)->create($order, ['purchasableId' => $line[0]->id, 'qty' => $line[1]]),
        $lines,
    ));

    expect($order->markAsComplete())->toBeTrue();

    return $order;
}

beforeEach(function() {
    app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid = false;
});

test('completing an order issues one license per unit of each variant that issues licenses', function() {
    $type = LicensesFixture::productType();
    $product = LicensesFixture::product($type);
    $licensed = LicensesFixture::variant($product, 'EDITOR-PRO');
    $unlicensed = LicensesFixture::variant($product, 'EDITOR-MUG', issuesLicense: false);

    $order = completeOrderFor('licensee@example.com', [[$licensed, 2], [$unlicensed, 3]]);

    /** @var License[] $licenses */
    $licenses = License::find()->orderId($order->id)->all();

    expect($licenses)->toHaveCount(2)
        ->and(array_map(fn(License $license) => $license->variantId, $licenses))->toBe([$licensed->id, $licensed->id])
        ->and($licenses[0]->licenseKey)->not->toBe($licenses[1]->licenseKey)
        ->and($licenses[0]->getProduct()?->id)->toBe($product->id)
        ->and($licenses[0]->getLicensedTo())->toBe('licensee@example.com')
        ->and(License::find()->product($product)->count())->toBe(2)
        ->and(License::find()->type('software')->count())->toBe(2)
        ->and(License::find()->variant('EDITOR-PRO')->count())->toBe(2)
        ->and(app(TwigRenderer::class)->renderString("{{ craft.licenses({variantId: $unlicensed->id}).count() }}|{{ craft.licenses({productId: $product->id}).count() }}"))->toBe('0|2');
});

test('a variant only issues licenses while its product type allows them', function() {
    $type = LicensesFixture::productType();
    $variant = LicensesFixture::variant(LicensesFixture::product($type), 'EDITOR-PRO');

    $type->hasLicenses = false;
    expect(app(ProductTypes::class)->saveProductType($type))->toBeTrue();
    $variant = Variant::find()->id($variant->id)->one();

    // The variant keeps its setting, so turning licenses back on for the type restores it
    expect($variant->issuesLicense)->toBeTrue()
        ->and($variant->issuesLicenses())->toBeFalse();

    $order = completeOrderFor('licensee@example.com', [[$variant, 1]]);

    expect(License::find()->orderId($order->id)->exists())->toBeFalse();
});

test('the Issues License field only shows on variants whose product type allows licenses', function() {
    $variant = LicensesFixture::variant(LicensesFixture::product(LicensesFixture::productType()), 'EDITOR-PRO');
    $other = LicensesFixture::variant(LicensesFixture::product(LicensesFixture::productType('apparel', hasLicenses: false), 'Shirt'), 'SHIRT', issuesLicense: false);

    expect($variant->getOwner()->getType()->getVariantFieldLayout()->getFirstVisibleElementByType(VariantIssuesLicenseField::class, $variant))->not->toBeNull()
        ->and($other->getOwner()->getType()->getVariantFieldLayout()->getFirstVisibleElementByType(VariantIssuesLicenseField::class, $other))->toBeNull()
        ->and(Variant::find()->issuesLicense()->ids())->toBe([$variant->id]);
});

test('a license needs a variant and an owner email or user', function() {
    $license = new License();
    $license->ruleset->useScenario(ElementRules::SCENARIO_LIVE);

    expect(Elements::saveElement($license))->toBeFalse()
        ->and($license->errors()->has('variantId'))->toBeTrue()
        ->and($license->errors()->has('userId'))->toBeTrue();
});

test('Commerce licenses stay off while the Digital Products plugin’s tables have data', function() {
    $licenses = app(Licenses::class);
    $variant = LicensesFixture::variant(LicensesFixture::product(LicensesFixture::productType()), 'EDITOR-PRO');

    Schema::create('digitalproducts_licenses', fn($table) => $table->integer('id'));
    DB::table('digitalproducts_licenses')->insert(['id' => 1]);
    $licenses->reset();

    $order = completeOrderFor('licensee@example.com', [[$variant, 1]]);

    expect($licenses->isEnabled())->toBeFalse()
        ->and(License::find()->orderId($order->id)->exists())->toBeFalse();

    DB::table('digitalproducts_licenses')->delete();
    $licenses->reset();

    expect($licenses->isEnabled())->toBeTrue();
});

describe('downloads', function() {
    beforeEach(function() {
        prioritizeCommerceRoutes();

        config()->set('filesystems.disks.downloads', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/downloads')]);
        Storage::fake('downloads');

        $volume = new Volume(['name' => 'Downloads', 'handle' => 'downloads', 'fsHandle' => 'downloads', 'hasUrls' => false]);
        app(Volumes::class)->saveVolume($volume) or throw new RuntimeException(json_encode($volume->errors()->all()));

        $createAsset = function(string $filename, string $contents) use ($volume): Asset {
            $path = tempnam(sys_get_temp_dir(), 'lic');
            file_put_contents($path, $contents);
            $asset = new Asset([
                'volumeId' => $volume->id,
                'folderId' => app(Folders::class)->getRootFolderByVolumeId($volume->id)->id,
                'filename' => $filename,
                'tempFilePath' => $path,
                'title' => $filename,
            ]);
            Elements::saveElement($asset) or throw new RuntimeException(json_encode($asset->errors()->all()));

            return $asset;
        };

        $this->filesField = new AssetsField(['name' => 'Files', 'handle' => 'files']);
        Fields::saveField($this->filesField) or throw new RuntimeException('Could not save field');
        $this->coverField = new AssetsField(['name' => 'Cover', 'handle' => 'cover']);
        Fields::saveField($this->coverField) or throw new RuntimeException('Could not save field');

        // The download file is on the variant; the cover image is on the product
        $type = LicensesFixture::productType();
        $type->setVariantFieldLayout(FieldLayout::createFromConfig(['type' => Variant::class, 'tabs' => [[
            'name' => 'Content',
            'elements' => [['type' => CustomField::class, 'fieldUid' => $this->filesField->uid]],
        ]]]));
        $type->setProductFieldLayout(FieldLayout::createFromConfig(['type' => \CraftCms\Commerce\Product\Elements\Product::class, 'tabs' => [[
            'name' => 'Content',
            'elements' => [['type' => CustomField::class, 'fieldUid' => $this->coverField->uid]],
        ]]]));
        app(ProductTypes::class)->saveProductType($type) or throw new RuntimeException(json_encode($type->errors()->all()));
        $this->type = app(ProductTypes::class)->getProductTypeById($type->id);

        $this->file = $createAsset('editor.zip', 'Editor download');
        $this->cover = $createAsset('cover.txt', 'Cover image');
        $this->unrelated = $createAsset('other.txt', 'Not for sale');

        $product = LicensesFixture::product($this->type);
        $product->setFieldValue('cover', [$this->cover->id]);
        Elements::saveElement($product) or throw new RuntimeException(json_encode($product->errors()->all()));

        $variant = LicensesFixture::variant($product, 'EDITOR-PRO');
        $variant->setFieldValue('files', [$this->file->id]);
        Elements::saveElement($variant) or throw new RuntimeException(json_encode($variant->errors()->all()));

        $this->license = new License();
        $this->license->variantId = $variant->id;
        $this->license->ownerEmail = 'customer@example.com';
        Elements::saveElement($this->license) or throw new RuntimeException(json_encode($this->license->errors()->all()));

        $this->download = fn(array $params) => $this->get('/actions/commerce/licenses/download?' . http_build_query(['licenseKey' => $this->license->licenseKey, ...$params]));
    });

    test('without a download field, any asset related to the variant or its product downloads, and assetId is required', function() {
        $response = ($this->download)(['assetId' => $this->file->id]);
        $response->assertOk()->assertDownload('editor.zip');
        expect($response->streamedContent())->toBe('Editor download');

        ($this->download)(['assetId' => $this->cover->id])->assertOk()->assertDownload('cover.txt');
        ($this->download)(['assetId' => $this->unrelated->id])->assertNotFound();
        ($this->download)([])->assertStatus(400);
        ($this->download)(['licenseKey' => 'nope', 'assetId' => $this->file->id])->assertNotFound();
    });

    test('with a variant download field, only its assets download, defaulting to the first', function() {
        $this->type->licenseDownloadFieldUid = $this->filesField->uid;
        app(ProductTypes::class)->saveProductType($this->type) or throw new RuntimeException(json_encode($this->type->errors()->all()));

        ($this->download)([])->assertOk()->assertDownload('editor.zip');
        ($this->download)(['assetId' => $this->file->id])->assertOk();
        ($this->download)(['assetId' => $this->cover->id])->assertNotFound();
    });

    test('with a product download field, the product’s assets download', function() {
        $this->type->licenseDownloadFieldUid = $this->coverField->uid;
        app(ProductTypes::class)->saveProductType($this->type) or throw new RuntimeException(json_encode($this->type->errors()->all()));

        ($this->download)([])->assertOk()->assertDownload('cover.txt');
        ($this->download)(['assetId' => $this->file->id])->assertNotFound();
    });

    test('the download field must be an Assets field in either layout', function() {
        $outside = new AssetsField(['name' => 'Elsewhere', 'handle' => 'elsewhere']);
        Fields::saveField($outside);

        $this->type->licenseDownloadFieldUid = $outside->uid;
        expect($this->type->validate())->toBeFalse()
            ->and($this->type->errors()->has('licenseDownloadFieldUid'))->toBeTrue();

        $this->type->licenseDownloadFieldUid = $this->coverField->uid;
        expect($this->type->validate())->toBeTrue();
    });
});
