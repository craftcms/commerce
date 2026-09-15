<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();

    $this->cart = new Order();
    $this->cart->storeId = $this->fixture->storeId;
    $this->cart->setCustomerId($this->fixture->customer->id);
    if (!Elements::saveElement($this->cart, false)) {
        throw new RuntimeException('Could not save cart: ' . json_encode($this->cart->errors()->all()));
    }
});

test('saveLineItem fails and returns false when qty exceeds the purchasable max qty', function() {
    // A brand new variant, with maxQty set at creation time - Purchasables::getPurchasableById()
    // memoizes per-request, and OrdersFixture::seed() already resolved/cached the fixture's own
    // white/blue variants for this store+customer, so mutating and re-saving one of those here
    // would be invisible to that already-warmed cache entry.
    $variant = new Variant();
    $variant->title = 'Limited';
    $variant->setPrimaryOwner($this->fixture->product);
    $variant->setSku('limited-edition');
    $variant->setBasePrice(9.99);
    $variant->siteId = Sites::getCurrentSite()->id;
    $variant->maxQty = 1;
    if (!Elements::saveElement($variant)) {
        throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
    }

    $lineItem = app(LineItems::class)->create($this->cart, [
        'purchasableId' => $variant->id,
        'qty' => 5,
    ]);
    $this->cart->setLineItems([$lineItem]);

    $saved = app(LineItems::class)->saveLineItem($lineItem, true);

    expect($saved)->toBeFalse();
    expect($lineItem->errors()->has('qty'))->toBeTrue();
});

test('saveLineItem succeeds and saves when qty is within limits', function() {
    $lineItem = app(LineItems::class)->create($this->cart, [
        'purchasableId' => $this->fixture->white->id,
        'qty' => 1,
    ]);
    $this->cart->setLineItems([$lineItem]);

    $saved = app(LineItems::class)->saveLineItem($lineItem, true);

    expect($saved)->toBeTrue();
    expect($lineItem->id)->not->toBeNull();
});

test('saveLineItem skips validation when $runValidation is false', function() {
    $variant = new Variant();
    $variant->title = 'Limited';
    $variant->setPrimaryOwner($this->fixture->product);
    $variant->setSku('limited-edition-2');
    $variant->setBasePrice(9.99);
    $variant->siteId = Sites::getCurrentSite()->id;
    $variant->maxQty = 1;
    if (!Elements::saveElement($variant)) {
        throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
    }

    $lineItem = app(LineItems::class)->create($this->cart, [
        'purchasableId' => $variant->id,
        'qty' => 5,
    ]);
    $this->cart->setLineItems([$lineItem]);

    $saved = app(LineItems::class)->saveLineItem($lineItem, false);

    expect($saved)->toBeTrue();
});
