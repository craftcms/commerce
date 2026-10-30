<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\SalesFixture;

beforeEach(function() {
    $this->fixture = SalesFixture::seed();
});

test('a variant with an active sale and no catalog pricing rules uses the sales system for its promotional and sale price', function() {
    $variant = Variant::find()->sku('rad-hood')->one();

    expect($variant->getPrice())->toEqual(123.99);
    expect($variant->getPromotionalPrice())->toEqual(111.59);
    expect($variant->getSalePrice())->toEqual(111.59);
});
