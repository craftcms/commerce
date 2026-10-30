<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('eagerLoadingMap resolves product/owner/primaryOwner to Product and falls through to the base implementation for other handles', function(string $handle, ?array $expected) {
    $variants = Variant::find()->all();

    $map = Variant::eagerLoadingMap($variants, $handle);

    if ($expected !== null) {
        expect($map)->not->toBeEmpty();
        foreach ($expected as $key => $value) {
            expect($map)->toHaveKey($key);
            expect($map[$key])->toEqual($value);
        }
    } else {
        expect($map)->toBeEmpty();
    }
})->with([
    'product' => ['product', ['elementType' => Product::class]],
    'owner' => ['owner', ['elementType' => Product::class]],
    'primaryOwner' => ['primaryOwner', ['elementType' => Product::class]],
    'an unrecognized handle falls through to the base implementation, which has nothing to eager-load' => ['customField', null],
]);
