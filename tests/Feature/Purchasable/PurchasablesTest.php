<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('getPurchasableById does not return a stale instance after the purchasable is re-saved', function() {
    $fixture = OrdersFixture::seed();

    $cached = app(Purchasables::class)->getPurchasableById($fixture->white->id);
    expect($cached->maxQty)->toBeNull();

    $fixture->white->maxQty = 3;
    expect(Elements::saveElement($fixture->white))->toBeTrue();

    $refetched = app(Purchasables::class)->getPurchasableById($fixture->white->id);

    expect($refetched->maxQty)->toBe(3);
});
