<?php

declare(strict_types=1);

use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Gql\Gql;
use CraftCms\Commerce\Gql\Resolvers\Elements\Product;
use CraftCms\Commerce\Product\Queries\ProductQuery;
use CraftCms\Commerce\Tests\Support\GqlProductsFixture;

beforeEach(function() {
    $this->fixture = GqlProductsFixture::seed();
    app(Gql::class)->flushCaches();
});

it('returns a ProductQuery for top-level resolution with full access schema', function() {
    gqlActivateFullAccessSchema();

    $query = Product::prepareQuery(null, []);

    expect($query)->toBeInstanceOf(ProductQuery::class);
});

it('returns empty collection when schema has no product type access', function() {
    gqlActivateSchema([]);

    $result = Product::prepareQuery(null, []);

    expect($result)->toBeInstanceOf(ElementCollection::class)
        ->and($result)->toBeEmpty();
});

it('restricts query to allowed product types based on schema', function() {
    gqlActivateSchema(["productTypes.{$this->fixture->hoodiesType->uid}:read"]);

    $ids = Product::prepareQuery(null, [])->ids();

    expect($ids)->toBe([$this->fixture->hoodie->id]);
});

it('returns preloaded data when source field is not a query', function() {
    gqlActivateFullAccessSchema();

    $preloaded = collect([(object)['id' => 1, 'title' => 'Test']]);

    $source = new stdClass();
    $source->products = $preloaded;

    $result = Product::prepareQuery($source, [], 'products');

    expect($result)->toBe($preloaded);
});

it('applies arguments as method calls on the query', function() {
    gqlActivateFullAccessSchema();

    $query = Product::prepareQuery(null, ['type' => 'hoodies']);

    expect($query)->toBeInstanceOf(ProductQuery::class)
        ->and($query->ids())->toBe([$this->fixture->hoodie->id]);
});

it('ignores null argument values without throwing', function() {
    gqlActivateFullAccessSchema();

    $query = Product::prepareQuery(null, ['nonExistentMethod' => null]);

    expect($query)->toBeInstanceOf(ProductQuery::class);
});

it('resolves the productTypeHandle and productTypeId fields via graphql', function() {
    gqlActivateFullAccessSchema();

    expect(graphQL('{products(type: "hoodies") {productTypeHandle productTypeId}}'))->toBe([
        'data' => [
            'products' => [
                ['productTypeHandle' => 'hoodies', 'productTypeId' => $this->fixture->hoodiesType->id],
            ],
        ],
    ]);
});
