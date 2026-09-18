<?php

declare(strict_types=1);

use CraftCms\Cms\Http\Controllers\NestedElementsController;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Elements\VariantCollection;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use Illuminate\Routing\Route;

function productVariantsProperty(Product $product): ?VariantCollection
{
    $reflection = new ReflectionClass($product);
    $property = $reflection->getProperty('_variants');

    return $property->getValue($product);
}

test('getVariants returns an empty collection for a product with no ID', function() {
    $product = new Product();

    $variants = $product->getVariants();

    expect($variants)->toBeInstanceOf(VariantCollection::class);
    expect($variants->isEmpty())->toBeTrue();
});

test('getVariants does not memoize an empty result, so it re-queries on the next call', function() {
    $product = new Product();
    $product->id = 999999;

    expect($product->getVariants()->isEmpty())->toBeTrue();
    expect(productVariantsProperty($product))->toBeNull();

    expect($product->getVariants()->isEmpty())->toBeTrue();
    expect(productVariantsProperty($product))->toBeNull();
});

test('getVariants memoizes a non-empty result', function() {
    $fixture = ProductConditionsFixture::seed();

    $variants1 = $fixture->hoodie->getVariants();
    expect($variants1->isEmpty())->toBeFalse();
    expect(productVariantsProperty($fixture->hoodie))->toBeInstanceOf(VariantCollection::class);

    $variants2 = $fixture->hoodie->getVariants();
    expect($variants2->count())->toBe($variants1->count());
    expect($variants2->first()->id)->toBe($variants1->first()->id);
});

test('getVariants on a product being duplicated fetches the source product\'s variants, not its own', function() {
    $fixture = ProductConditionsFixture::seed();

    $duplicate = new Product();
    $duplicate->id = 999998;
    $duplicate->typeId = $fixture->hoodie->typeId;
    $duplicate->siteId = 999999;
    $duplicate->duplicateOf = $fixture->hoodie;

    $variants = $duplicate->getVariants();

    expect($variants->pluck('id')->all())->toBe($fixture->hoodie->getVariants()->pluck('id')->all());
});

test('getVariants can include or exclude disabled variants', function() {
    $fixture = ProductConditionsFixture::seed();

    $disabled = new Variant();
    $disabled->title = 'Disabled Variant';
    $disabled->sku = 'disabled-variant-sku';
    $disabled->enabled = false;
    $disabled->setOwner($fixture->hoodie);

    $variants = $fixture->hoodie->getVariants()->all();
    $variants[] = $disabled;
    $fixture->hoodie->setVariants($variants);

    expect($fixture->hoodie->getVariants(false)->every(fn(Variant $v) => $v->enabled))->toBeTrue();
    expect($fixture->hoodie->getVariants(true)->contains(fn(Variant $v) => !$v->enabled))->toBeTrue();
});

test('a null includeDisabled resolves based on whether NestedElementsController is the active controller', function(?bool $includeDisabled, ?string $controllerClass, int $expectedCount) {
    request()->setRouteResolver(function() use ($controllerClass) {
        if ($controllerClass === null) {
            return null;
        }

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getControllerClass')->andReturn($controllerClass);

        return $route;
    });

    $product = new Product();

    $enabled = new Variant();
    $enabled->enabled = true;
    $enabled->sku = 'enabled-sku';

    $disabled = new Variant();
    $disabled->enabled = false;
    $disabled->sku = 'disabled-sku';

    $product->setVariants([$enabled, $disabled]);

    expect($product->getVariants($includeDisabled))->toHaveCount($expectedCount);

    // The includeDisabled filter must never mutate the internal collection — both variants remain
    // regardless of which ones are returned.
    expect(productVariantsProperty($product))->toHaveCount(2);
})->with([
    'null, no active controller, excludes disabled' => [null, null, 1],
    'null, NestedElementsController active, includes disabled' => [null, NestedElementsController::class, 2],
    'explicit false overrides an active NestedElementsController' => [false, NestedElementsController::class, 1],
    'explicit true works without an active controller' => [true, null, 2],
]);
