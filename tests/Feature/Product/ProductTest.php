<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product type, optionally with a variant SKU format, for tests that exercise
 * `Product::prepareForValidation()`'s SKU generation.
 */
function createSkuFormatProductType(string $handle, ?string $skuFormat): ProductType
{
    $site = Sites::getCurrentSite();

    $productType = new ProductType();
    $productType->name = $handle;
    $productType->handle = $handle;
    $productType->skuFormat = $skuFormat;
    $productType->hasVariantTitleField = false;
    $productType->variantTitleFormat = '{product.title}';

    $siteSettings = new ProductTypeSite();
    $siteSettings->siteId = $site->id;
    $siteSettings->hasUrls = false;
    $siteSettings->enabledByDefault = true;
    $productType->setSiteSettings([$site->id => $siteSettings]);

    if (!app(ProductTypes::class)->saveProductType($productType)) {
        throw new RuntimeException('Could not save product type: ' . json_encode($productType->errors()->all()));
    }

    return $productType;
}

/**
 * Deletes any existing sequence counter for a SKU base, so a deduplication suffix is predictable.
 */
function resetSkuSequence(string $baseSku): void
{
    DB::table(CraftTable::SEQUENCES)->where('name', 'sku::' . $baseSku)->delete();
}

test('a product with a fully populated variant validates without errors', function() {
    $fixture = ProductConditionsFixture::seed();

    $product = new Product();
    $product->enabled = false;
    $product->title = 'test';
    $product->typeId = $fixture->hoodiesType->id;

    $variant = new Variant();
    $variant->title = 'variant 1';
    $product->setVariants([$variant]);

    $product->validate();

    expect($product->errors()->all())->toBeEmpty();
});

test('constructing a product from an array mass-assigns its properties and nested variants', function() {
    $productType = createSkuFormatProductType('massAssignment', null);

    $product = new Product([
        'title' => 'Test Product',
        'typeId' => $productType->id,
        'enabled' => true,
        'variants' => [
            [
                'title' => 'Test Variant',
                'basePrice' => 123,
                'sku' => '123',
                'enabled' => true,
            ],
        ],
    ]);

    expect($product->title)->toBe('Test Product');
    expect($product->typeId)->toBe($productType->id);
    expect($product->enabled)->toBeTrue();

    $variants = $product->getVariants(true);
    expect($variants)->toHaveCount(1);

    $variant = $variants->first();
    expect($variant->title)->toBe('Test Variant');
    expect($variant->basePrice)->toEqual(123);
    expect($variant->sku)->toBe('123');
    expect($variant->enabled)->toBeTrue();
});

test('getVariants, getDefaultVariant and getCheapestVariant reflect each variant\'s default/enabled flags', function(array $variantData, array $expected) {
    $product = new Product();
    $product->enabled = true;
    $product->typeId = 2001;
    $product->title = 'Test Product';

    $variants = [];
    $count = 1;
    $defaultVariantId = null;
    foreach ($variantData as [$id, $price, $default, $enabled]) {
        $variant = new Variant();
        $variant->id = $id;
        $variant->title = sprintf('Test Variant #%s', $count);
        $variant->isDefault = $default;
        $defaultVariantId = $default ? $id : $defaultVariantId;
        $variant->enabled = $enabled;
        $variant->price = $price;

        $variants[] = $variant;
        $count++;
    }

    $product->setVariants($variants);
    if ($defaultVariantId) {
        $product->defaultVariantId = $defaultVariantId;
    }

    expect($product->getVariants(true))->toHaveCount($expected['variantCount']);
    expect($product->getVariants())->toHaveCount($expected['enabledVariantCount']);

    $defaultVariant = $product->getDefaultVariant(true);
    expect($defaultVariant->title)->toBe($expected['defaultVariantTitle']);

    $cheapestVariant = $product->getCheapestVariant(true);
    expect($cheapestVariant->title)->toBe($expected['cheapestVariantTitle']);

    $defaultEnabledVariant = $product->getDefaultVariant();
    expect($defaultEnabledVariant->title ?? null)->toBe($expected['defaultEnabledVariantTitle']);

    $cheapestEnabledVariant = $product->getCheapestVariant();
    expect($cheapestEnabledVariant->title ?? null)->toBe($expected['cheapestEnabledVariantTitle']);
})->with([
    'all enabled' => [
        [[1001, 123, true, true], [1002, 456, false, true], [1003, 789, false, true]],
        [
            'variantCount' => 3,
            'enabledVariantCount' => 3,
            'cheapestVariantTitle' => 'Test Variant #1',
            'defaultVariantTitle' => 'Test Variant #1',
            'cheapestEnabledVariantTitle' => 'Test Variant #1',
            'defaultEnabledVariantTitle' => 'Test Variant #1',
        ],
    ],
    'one disabled' => [
        [[1001, 123, false, false], [1002, 456, false, true], [1003, 789, true, true]],
        [
            'variantCount' => 3,
            'enabledVariantCount' => 2,
            'cheapestVariantTitle' => 'Test Variant #1',
            'defaultVariantTitle' => 'Test Variant #3',
            'cheapestEnabledVariantTitle' => 'Test Variant #2',
            'defaultEnabledVariantTitle' => 'Test Variant #3',
        ],
    ],
    'all disabled' => [
        [[1001, 123, false, false], [1002, 456, true, false], [1003, 99, false, false]],
        [
            'variantCount' => 3,
            'enabledVariantCount' => 0,
            'cheapestVariantTitle' => 'Test Variant #3',
            'defaultVariantTitle' => 'Test Variant #2',
            'cheapestEnabledVariantTitle' => null,
            'defaultEnabledVariantTitle' => null,
        ],
    ],
]);

test('saving a variant updates its owning product\'s denormalized default-variant data, and keeps it in sync on later saves', function() {
    $productType = createSkuFormatProductType('saveProductAndVariants', null);

    $product = new Product();
    $product->title = 'Test Product';
    $product->typeId = $productType->id;
    $product->slug = 'test-product';
    $product->enabled = true;
    $product->enabledForSite = true;
    $product->postDate = new DateTime('now');

    expect(Elements::saveElement($product, false))->toBeTrue();

    $variant = new Variant();
    $variant->title = 'Test Variant';
    $variant->slug = 'test-variant';
    $variant->setPrimaryOwner($product);
    $variant->setSku('test-variant-sku');
    $variant->setBasePrice(99.99);
    $variant->sortOrder = 0;
    $variant->inventoryTracked = false;
    $variant->isDefault = true;

    $variant2 = new Variant();
    $variant2->title = 'Test Variant 2';
    $variant2->slug = 'test-variant-2';
    $variant2->setPrimaryOwner($product);
    $variant2->setSku('test-variant-sku2');
    $variant2->setBasePrice(100.99);
    $variant2->sortOrder = 1;
    $variant2->inventoryTracked = false;
    $variant2->isDefault = false;

    expect(Elements::saveElement($variant, false))->toBeTrue();
    expect(Elements::saveElement($variant2, false))->toBeTrue();

    $productData = DB::table(Table::PRODUCTS)
        ->select(['defaultVariantId', 'defaultSku', 'defaultPrice', 'defaultWidth', 'defaultHeight', 'defaultLength', 'defaultWeight'])
        ->where('id', $product->id)
        ->first();

    // Check the product data in the database
    expect($productData->defaultVariantId)->toEqual($variant->id);
    expect($productData->defaultSku)->toBe('test-variant-sku');
    expect((float)$productData->defaultPrice)->toEqual(99.99);
    expect((float)$productData->defaultWidth)->toEqual(0);
    expect((float)$productData->defaultHeight)->toEqual(0);
    expect((float)$productData->defaultLength)->toEqual(0);
    expect((float)$productData->defaultWeight)->toEqual(0);

    // Check a freshly-queried product object reflects the same data
    $reloadedProduct = Product::find()->id($product->id)->one();
    expect($reloadedProduct->getDefaultVariant()->id)->toEqual($variant->id);
    expect($reloadedProduct->defaultSku)->toBe('test-variant-sku');
    expect($reloadedProduct->defaultPrice)->toEqual(99.99);

    // Make changes and independently save the default variant to check the product data is updated
    $variant->setSku('test-variant-sku-updated');
    $variant->setBasePrice(199.99);

    expect(Elements::saveElement($variant, false))->toBeTrue();

    $newProductData = DB::table(Table::PRODUCTS)
        ->select(['defaultVariantId', 'defaultSku', 'defaultPrice', 'defaultWidth', 'defaultHeight', 'defaultLength', 'defaultWeight'])
        ->where('id', $product->id)
        ->first();

    expect($newProductData->defaultVariantId)->toEqual($variant->id);
    expect($newProductData->defaultSku)->toBe('test-variant-sku-updated');
    expect((float)$newProductData->defaultPrice)->toEqual(199.99);
    expect((float)$newProductData->defaultWidth)->toEqual(0);
    expect((float)$newProductData->defaultHeight)->toEqual(0);
    expect((float)$newProductData->defaultLength)->toEqual(0);
    expect((float)$newProductData->defaultWeight)->toEqual(0);

    Elements::deleteElementById($product->id, Product::class, null, true);
});

test('an empty SKU is generated from the product type\'s SKU format', function() {
    $productType = createSkuFormatProductType('skuFormatGenerated', 'generated-sku-from-format');

    $product = new Product();
    $product->title = 'SKU Format Test Product';
    $product->typeId = $productType->id;
    $product->enabled = false;

    $variant = new Variant();
    $variant->title = 'Test Variant';
    // SKU intentionally not set — should be generated from skuFormat

    $product->setVariants([$variant]);
    $product->validate();

    expect($variant->sku)->toBe('generated-sku-from-format');
});

test('a generated SKU is deduplicated when it collides with an existing SKU', function() {
    // ProductConditionsFixture saves a variant with the 'rad-hood' SKU already.
    ProductConditionsFixture::seed();
    resetSkuSequence('rad-hood');

    $productType = createSkuFormatProductType('skuFormatCollision', 'rad-hood');

    $product = new Product();
    $product->title = 'Collision Test Product';
    $product->typeId = $productType->id;
    $product->enabled = false;

    $variant = new Variant();
    $variant->title = 'Test Variant';
    // No SKU — format generates 'rad-hood', which collides with the fixture's variant

    $product->setVariants([$variant]);
    $product->validate();

    expect($variant->sku)->toBe('rad-hood-1');
});

test('generated SKUs are deduplicated independently for each colliding variant on the same product', function() {
    // VariantQueryFixture saves a variant with the 'hct-white' SKU already.
    VariantQueryFixture::seed();
    resetSkuSequence('hct-white');

    $productType = createSkuFormatProductType('skuFormatMultiCollision', 'hct-white');

    $product = new Product();
    $product->title = 'Multi-Variant Collision Test';
    $product->typeId = $productType->id;
    $product->enabled = false;

    $variant1 = new Variant();
    $variant1->title = 'Variant One';

    $variant2 = new Variant();
    $variant2->title = 'Variant Two';

    $product->setVariants([$variant1, $variant2]);
    $product->validate();

    expect($variant1->sku)->toBe('hct-white-1');
    expect($variant2->sku)->toBe('hct-white-2');
});

test('a SKU format referencing {id} is regenerated once the variant has been assigned one', function() {
    $productType = createSkuFormatProductType('skuFormatWithId', 'SKU-{id}');

    $product = new Product();
    $product->title = 'SKU Format Id Test Product';
    $product->typeId = $productType->id;
    $product->enabled = false;

    expect(Elements::saveElement($product, false))->toBeTrue();

    $variant = new Variant();
    $variant->title = 'Test Variant';
    $variant->setPrimaryOwner($product);
    // No SKU — format references {id}, which isn't available until after the element is saved

    expect(Elements::saveElement($variant, false))->toBeTrue();

    expect($variant->sku)->toBe('SKU-' . $variant->id);

    Elements::deleteElementById($product->id, Product::class, null, true);
});
