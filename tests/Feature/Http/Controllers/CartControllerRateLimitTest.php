<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

use function Pest\Laravel\postJson;

beforeEach(function() {
    prioritizeCommerceRoutes();
});

it('allows the request that first creates a cart, since no cart number is present yet', function() {
    $fixture = OrdersFixture::seed();

    postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'purchasableId' => $fixture->white->id,
        'qty' => 1,
    ])->assertOk();
});

it('rate limits rapid repeat requests that carry the same cart number', function() {
    $fixture = OrdersFixture::seed();

    $created = postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'purchasableId' => $fixture->white->id,
        'qty' => 1,
    ])->assertOk();

    $number = $created->json('cart.number');

    // The first request naming a cart `number` is within the allowance.
    postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'number' => $number,
        'qty' => 2,
    ])->assertOk();

    // An immediate second request for that same `number` exceeds it.
    postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'number' => $number,
        'qty' => 3,
    ])->assertStatus(429);
});

it('never rate limits requests that carry neither a number nor a coupon code', function() {
    $fixture = OrdersFixture::seed();

    for ($i = 0; $i < 3; $i++) {
        postJson(Url::actionUrl('commerce/cart/update-cart'), [
            'purchasableId' => $fixture->white->id,
            'qty' => 1,
        ])->assertOk();
    }
});
