<?php

declare(strict_types=1);

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\StoresFixture;

/**
 * Builds one product type spanning three sites — two under the primary store, one under a second
 * store — with a product and two variants that propagate to all of them, for testing how the
 * `site` query scope interacts with each result's resolved store.
 *
 * @return array{0: StoresFixture, 1: Site}
 */
function seedVariantSiteQueryScenario(): array
{
    $stores = StoresFixture::seed();

    // New sites default to the primary store (see StoresFixture::assignSiteToStore()), so this
    // needs no explicit site-to-store mapping.
    $secondPrimarySite = new Site([
        'name' => 'Second Primary Site',
        'handle' => 'secondPrimarySite',
        'language' => 'en-US',
        'baseUrl' => 'https://secondPrimarySite.test',
        'hasUrls' => true,
        'groupId' => $stores->usSite->groupId,
    ]);
    if (!Sites::saveSite($secondPrimarySite)) {
        throw new RuntimeException('Could not save site: ' . json_encode($secondPrimarySite->errors()->all()));
    }

    $productType = new ProductType();
    $productType->name = 'Multi-Site Widgets';
    $productType->handle = 'multiSiteWidgets';
    $productType->hasVariantTitleField = true;
    $productType->variantTitleFormat = '{product.title} - {title}';
    $productType->setSiteSettings([
        $stores->usSite->id => variantSiteQueryProductTypeSite($stores->usSite->id),
        $secondPrimarySite->id => variantSiteQueryProductTypeSite($secondPrimarySite->id),
        $stores->euSite->id => variantSiteQueryProductTypeSite($stores->euSite->id),
    ]);
    if (!app(ProductTypes::class)->saveProductType($productType)) {
        throw new RuntimeException('Could not save product type: ' . json_encode($productType->errors()->all()));
    }

    $product = new Product();
    $product->typeId = $productType->id;
    $product->title = 'Widget';
    $product->enabled = true;
    $product->siteId = $stores->usSite->id;
    if (!Elements::saveElement($product)) {
        throw new RuntimeException('Could not save product: ' . json_encode($product->errors()->all()));
    }

    foreach (['Red' => true, 'Blue' => false] as $title => $isDefault) {
        $variant = new Variant();
        $variant->title = $title;
        $variant->setPrimaryOwner($product);
        $variant->setSku('widget-' . strtolower($title));
        $variant->setBasePrice(10.0);
        $variant->isDefault = $isDefault;
        $variant->promotable = true;
        $variant->siteId = $stores->usSite->id;
        if (!Elements::saveElement($variant)) {
            throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
        }
    }

    return [$stores, $secondPrimarySite];
}

function variantSiteQueryProductTypeSite(int $siteId): ProductTypeSite
{
    $siteSettings = new ProductTypeSite();
    $siteSettings->siteId = $siteId;
    $siteSettings->hasUrls = false;
    $siteSettings->enabledByDefault = true;

    return $siteSettings;
}

beforeEach(function() {
    [$this->stores, $this->secondPrimarySite] = seedVariantSiteQueryScenario();
});

test('site scopes results to variants existing in the given site', function() {
    $ids = Variant::find()->site($this->stores->usSite->handle)->ids();

    expect($ids)->toHaveCount(2);
});

test('site accepts multiple sites under the same store, and every result resolves to that store', function() {
    $variants = Variant::find()->site([$this->stores->usSite->handle, $this->secondPrimarySite->handle])->all();

    expect($variants)->toHaveCount(4);
    foreach ($variants as $variant) {
        expect($variant->getStore()->handle)->toBe($this->stores->primaryStore->handle);
    }
});

test('site accepts multiple sites across different stores, and each result resolves to its own store', function() {
    $variants = Variant::find()->site([$this->stores->usSite->handle, $this->stores->euSite->handle])->all();

    expect($variants)->toHaveCount(4);

    $storeHandleBySite = [];
    foreach ($variants as $variant) {
        $storeHandleBySite[$variant->getSite()->handle] = $variant->getStore()->handle;
    }

    expect($storeHandleBySite)->toBe([
        $this->stores->usSite->handle => $this->stores->primaryStore->handle,
        $this->stores->euSite->handle => $this->stores->euStore->handle,
    ]);
});
