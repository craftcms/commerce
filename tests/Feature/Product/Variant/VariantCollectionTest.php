<?php

declare(strict_types=1);

use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Elements\VariantCollection;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;

beforeEach(function() {
    $this->fixture = VariantQueryFixture::seed();
});

test('collect() returns a VariantCollection', function() {
    $collection = Variant::find()->limit(4)->collect();

    expect($collection)->toBeInstanceOf(VariantCollection::class);
    expect($collection)->toBeInstanceOf(ElementCollection::class);
});

test('cheapest() returns the variant with the lowest sale price', function() {
    $collection = Variant::find()->collect();

    expect($collection)->toBeInstanceOf(VariantCollection::class);
    expect($collection->cheapest())->not->toBeNull();
    expect($collection->cheapest()->getSku())->toBe('hct-white');
});

test('make() builds variants from raw attributes, including custom field values', function() {
    $field = new PlainText([
        'name' => 'My Variant Heading Field',
        'handle' => 'myVariantHeadingField',
    ]);
    expect(app(Fields::class)->saveField($field))->toBeTrue();

    $fieldLayout = new FieldLayout(['type' => Variant::class]);
    $fieldLayout->tab(FieldLayout::defaultTabName(), fn($tab) => $tab->field($field->handle));

    // Built as its own product type (rather than attaching the layout to, and re-saving, the
    // shared fixture's already-saved product type): re-validating the same in-memory
    // ProductType instance a second time reuses its first call's cached validator, including a
    // unique-handle rule that ignores the pre-save `null` id, so re-saving an already-saved
    // instance fails validation even though nothing actually conflicts. It needs to pick up
    // this field layout on its one and only save instead.
    $site = Sites::getCurrentSite();
    $productType = new ProductType();
    $productType->name = 'Field Test Hoodies';
    $productType->handle = 'fieldTestHoodies';
    $productType->hasVariantTitleField = false;
    $productType->variantTitleFormat = '{product.title}';
    $productType->setVariantFieldLayout($fieldLayout);

    $siteSettings = new ProductTypeSite();
    $siteSettings->siteId = $site->id;
    $siteSettings->hasUrls = false;
    $siteSettings->enabledByDefault = true;
    $productType->setSiteSettings([$site->id => $siteSettings]);

    expect(app(ProductTypes::class)->saveProductType($productType))->toBeTrue();

    $product = new Product();
    $product->typeId = $productType->id;
    $product->title = 'Rad Hoodie';
    $product->enabled = true;
    $product->siteId = $site->id;
    expect(Elements::saveElement($product))->toBeTrue();
    $product = Product::find()->id($product->id)->one();

    $attrs = [
        'ownerId' => $product->id,
        'owner' => $product,
        'primaryOwnerId' => $product->id,
        'primaryOwner' => $product,
        'title' => 'Test Variant',
        'basePrice' => 123.0,
        'sku' => '123',
        'enabled' => true,
        'myVariantHeadingField' => 'bar',
    ];

    $collection = VariantCollection::make([$attrs]);

    expect($collection)->toBeInstanceOf(VariantCollection::class);

    $variant = $collection->first();
    foreach (['title', 'basePrice', 'sku', 'myVariantHeadingField'] as $key) {
        expect($variant->$key)->toEqual($attrs[$key]);
    }
});
