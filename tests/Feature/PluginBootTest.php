<?php

declare(strict_types=1);

use CraftCms\Cms\Element\ElementTypes;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Store\Data\Store;

test('Plugin::boot() registrations actually take effect in tests', function() {
    $types = app(ElementTypes::class)->types();

    expect($types->contains(Product::class))->toBeTrue();
});

test('Site::getStore() macro resolves via method call', function() {
    $site = Sites::getCurrentSite();

    expect($site->getStore())->toBeInstanceOf(Store::class);
});

test('Site::getStore() macro resolves via magic property access', function() {
    $site = Sites::getCurrentSite();

    expect($site->store)->toBeInstanceOf(Store::class);
});
