<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function productsControllerTestProductType(string $handle, string $name): ProductType
{
    $siteId = Sites::getPrimarySite()->id;

    $productType = new ProductType();
    $productType->name = $name;
    $productType->handle = $handle;
    $productType->hasVariantTitleField = false;
    $productType->variantTitleFormat = '{product.title}';

    $siteSettings = new ProductTypeSite();
    $siteSettings->siteId = $siteId;
    $siteSettings->hasUrls = false;
    $siteSettings->enabledByDefault = true;
    $productType->setSiteSettings([$siteId => $siteSettings]);

    app(ProductTypes::class)->saveProductType($productType);

    return $productType;
}

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->hoodies = productsControllerTestProductType('hoodies', 'Hoodies');
    $this->tShirts = productsControllerTestProductType('tShirts', 'T-Shirts');

    $this->indexPath = '/' . Cms::config()->cpTrigger . '/commerce/products';
});

it('renders the products index page', function() {
    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::products/Index', false)
            ->where('elementType', Product::class)
            ->where('indexUrl', Url::cpUrl('commerce/products'))
            ->where('productTypeHandle', '')
            ->where('source.key', '*')
            ->has('sourceNavItems', 2)
            ->where('sourceNavItems.0.label', 'All products')
            ->where('sourceNavItems.0.href', Url::cpUrl('commerce/products'))
            ->where('sourceNavItems.0.selected', true)
            ->where('sourceNavItems.1.label', 'Product Types')
            ->where('sourceNavItems.1.group', true)
            ->has('sourceNavItems.1.subnav', 2)
            ->where('sourceNavItems.1.subnav.0.label', 'Hoodies')
            ->where('sourceNavItems.1.subnav.0.href', Url::cpUrl('commerce/products/hoodies'))
            ->where('sourceNavItems.1.subnav.0.selected', false)
            ->where('sourceNavItems.1.subnav.1.label', 'T-Shirts')
            ->where('sourceNavItems.1.subnav.1.href', Url::cpUrl('commerce/products/tShirts'))
            ->where('sourceNavItems.1.subnav.1.selected', false)
            ->where('crumbs.0.label', 'Commerce')
            ->where('creatableProductTypes.0.handle', 'hoodies')
            ->where('creatableProductTypes.0.newUrl', Url::cpUrl('commerce/products/hoodies/new'))
            ->where('creatableProductTypes.0.newLabel', 'New Hoodies product')
            ->where('creatableProductTypes.0.sourceKey', 'productType:' . $this->hoodies->uid)
            ->where('newProductLabel', 'New product')
            ->where('newProductMenuLabel', 'New product, choose a type')
            ->where('creatableProductTypes.1.handle', 'tShirts')
            ->has('data')
            ->has('sources')
        );
});

it('selects a product type source from the handle segment', function() {
    get($this->indexPath . '/hoodies')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('productTypeHandle', 'hoodies')
            ->where('source.key', 'productType:' . $this->hoodies->uid)
            ->where('title', 'Hoodies')
            ->where('sourceNavItems.0.selected', false)
            ->where('sourceNavItems.1.subnav.0.selected', true)
            ->where('sourceNavItems.1.subnav.1.selected', false)
            ->where('crumbs.2.label', 'Hoodies')
            ->where('crumbs.2.href', null)
            ->where('crumbs.2.items.1.items.0.href', Url::cpUrl('commerce/products/hoodies'))
            ->where('crumbs.2.items.1.items.0.selected', true)
        );
});

it('falls back to all products for an unknown product type handle', function() {
    get($this->indexPath . '/nope')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where('source.key', '*'));
});

it('prefers the source query over the handle segment', function() {
    get($this->indexPath . '/hoodies?source=' . urlencode('productType:' . $this->tShirts->uid))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('source.key', 'productType:' . $this->tShirts->uid)
        );
});

it('requires a viewable product type', function() {
    $productTypes = Mockery::mock(ProductTypes::class)->makePartial();
    $productTypes->shouldReceive('getViewableProductTypeIds')->andReturn([]);
    app()->instance(ProductTypes::class, $productTypes);

    get($this->indexPath)->assertForbidden();
});

it('only offers product types the user can create products in', function() {
    $hoodiesId = $this->hoodies->id;

    Gate::before(fn($user, string $ability, array $arguments) => $ability === 'save'
        && ($arguments[0] ?? null) instanceof Product
        && $arguments[0]->typeId === $hoodiesId ? false : null);

    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->has('creatableProductTypes', 1)
            ->where('creatableProductTypes.0.handle', 'tShirts')
        );
});

it('builds product type source URLs from the handle', function() {
    expect(Product::sourceCpUri(['key' => 'productType:abc', 'data' => ['handle' => 'hoodies']]))
        ->toBe('commerce/products/hoodies')
        ->and(Product::sourceCpUri(['key' => 'custom:abc', 'data' => ['handle' => 'hoodies']]))->toBeNull()
        ->and(Product::sourceCpUri(['key' => '*']))->toBeNull();
});

it('resolves an unsaved product’s store from its site', function() {
    $product = new Product();
    $product->typeId = $this->hoodies->id;
    $product->siteId = Sites::getPrimarySite()->id;

    expect($product->getStore()->id)->toBe(app(Stores::class)->getStoreBySiteId($product->siteId)->id);
});
