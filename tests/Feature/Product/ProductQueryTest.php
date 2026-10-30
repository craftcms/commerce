<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Queries\ProductQuery;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('find returns a ProductQuery', function() {
    expect(Product::find())->toBeInstanceOf(ProductQuery::class);
});

test('defaultPrice filters products by their default variant price', function(mixed $price, int $count) {
    $query = Product::find()->defaultPrice($price);

    expect($query->all())->toHaveCount($count);
})->with([
    'exact match' => [123.99, 1],
    'exact match, no results' => [999, 0],
    'greater than, matches both' => ['> 1', 2],
    'greater than, no results' => ['> 999', 0],
    'less than, matches both' => ['< 150', 2],
    'less than, no results' => ['< 1', 0],
    'range, matches both' => [['and', '> 5', '< 200'], 2],
    'range, no results' => [['and', '> 500', '< 2000'], 0],
    'in, matches both' => [[123.99, 19.99], 2],
    'in, no results' => [[1, 2], 0],
]);

test('hasVariant filters products to those matching a variant query', function(Closure $variantQuery, int $count) {
    $query = Product::find()->hasVariant($variantQuery());

    expect($query->all())->toHaveCount($count);
})->with([
    'no criteria matches every product with a variant' => [fn() => Variant::find(), 2],
    'sku criteria narrows to a single product' => [fn() => Variant::find()->sku('rad-hood'), 1],
]);

test('with(variants) eager loads each product\'s variants', function() {
    $results = Product::find()->with(['variants'])->all();

    expect($results)->toHaveCount(2);
    foreach ($results as $product) {
        expect($product->getVariants()->first())->toBeInstanceOf(Variant::class);
    }
});

test('with(variants) narrowed by a variant query only eager loads and returns matching products', function() {
    $query = Product::find()->hasVariant(['sku' => 'rad-hood']);
    $query->with([['variants', ['sku' => 'rad-hood']]]);

    $results = $query->all();

    expect($results)->toHaveCount(1);
    expect($results[0]->title)->toBe($this->fixture->hoodie->title);
    expect($results[0]->getVariants()->first())->toBeInstanceOf(Variant::class);
});

test('orderBy sorts products', function(array $orderBy, array $expectedTitles) {
    $results = Product::find()->orderBy($orderBy)->all();

    $titles = collect($results)->map(fn(Product $p) => $p->title)->all();

    expect($titles)->toBe($expectedTitles);
})->with([
    'title ascending' => [['title' => SORT_ASC], ['Plain T-Shirt', 'Rad Hoodie']],
    'title descending' => [['title' => SORT_DESC], ['Rad Hoodie', 'Plain T-Shirt']],
    'default price ascending' => [['defaultPrice' => SORT_ASC], ['Plain T-Shirt', 'Rad Hoodie']],
    'default price descending' => [['defaultPrice' => SORT_DESC], ['Rad Hoodie', 'Plain T-Shirt']],
]);
