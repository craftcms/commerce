<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->fixture = ProductConditionsFixture::seed();
    $this->indexPath = '/' . Cms::config()->cpTrigger . '/commerce/variants';
});

it('renders the variants index without a global create action', function() {
    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::variants/Index', false)
            ->where('elementType', Variant::class)
            ->where('indexUrl', Url::cpUrl('commerce/variants'))
            ->where('productTypeHandle', '')
            ->where('source.key', '*')
            ->where('crumbs.0.label', 'Commerce')
            ->has('data')
            ->has('sources')
            ->missing('newVariantUrl')
        );
});

it('selects a product type source from the handle segment', function() {
    get($this->indexPath . '/hoodies')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('productTypeHandle', 'hoodies')
            ->where('source.key', 'productType:' . $this->fixture->hoodiesType->uid)
            ->where('title', 'Hoodies')
        );
});

it('requires a viewable product type', function() {
    $productTypes = Mockery::mock(ProductTypes::class)->makePartial();
    $productTypes->shouldReceive('getViewableProductTypeIds')->andReturn([]);
    app()->instance(ProductTypes::class, $productTypes);

    get($this->indexPath)->assertForbidden();
});

it('opens a variant with the shared element editor', function() {
    $variant = $this->fixture->hoodie->getDefaultVariant();

    get($this->indexPath . '/' . $variant->id)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('elements/Edit', false)
            ->where('elementType', Variant::class)
            ->where('canonicalId', $variant->id)
        );
});
