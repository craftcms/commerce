<?php

declare(strict_types=1);

use CraftCms\Commerce\Tests\Support\GqlProductsFixture;

beforeEach(function() {
    $this->fixture = GqlProductsFixture::seed();
    gqlActivateFullAccessSchema();
});

it('queries products', function() {
    $result = graphQL('{products{title}}');

    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['products'])->toBeArray();
});

it('queries variants', function() {
    $result = graphQL('{variants{title}}');

    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['variants'])->toBeArray();
});

it('returns a graphql error for an unknown product field', function() {
    $result = graphQL('{products{bogus}}');

    expect($result['errors'][0]['message'])->toContain('Cannot query field "bogus"');
});

it('returns a graphql error for an invalid query argument type', function() {
    $result = graphQL('{products(limit:[5,2]){title}}');

    expect($result['errors'][0]['message'])->toContain('Int cannot represent non-integer value');
});

it('filters products by product type handle', function() {
    expect(graphQL('{products(type: "hoodies") {title slug}}'))->toBe([
        'data' => [
            'products' => [
                ['title' => 'Rad Hoodie', 'slug' => 'rad-hoodie'],
            ],
        ],
    ]);

    expect(graphQL('{products(type: "tShirts") {title slug}}'))->toBe([
        'data' => [
            'products' => [
                ['title' => 'Hypercolor T-Shirt', 'slug' => 'hypercolor-tshirt'],
            ],
        ],
    ]);
});

it('resolves variant title, sku, and availability', function() {
    expect(graphQL('{variants{title sku}}'))->toBe([
        'data' => [
            'variants' => [
                ['title' => 'Rad Hoodie', 'sku' => 'rad-hood'],
                ['title' => 'Hypercolor T-Shirt', 'sku' => 'hct-white'],
                ['title' => 'Hypercolor T-Shirt', 'sku' => 'hct-blue'],
            ],
        ],
    ]);

    expect(graphQL('{variants{sku promotable availableForPurchase}}'))->toBe([
        'data' => [
            'variants' => [
                ['sku' => 'rad-hood', 'promotable' => true, 'availableForPurchase' => true],
                ['sku' => 'hct-white', 'promotable' => true, 'availableForPurchase' => true],
                ['sku' => 'hct-blue', 'promotable' => true, 'availableForPurchase' => true],
            ],
        ],
    ]);
});
