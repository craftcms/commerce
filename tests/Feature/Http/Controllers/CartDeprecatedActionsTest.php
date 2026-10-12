<?php

declare(strict_types=1);

use CraftCms\Cms\RouteToken\RouteTokens;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\CartsFixture;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

beforeEach(function() {
    prioritizeCommerceRoutes();
});

it('serves a deprecated cart action the same as its replacement and logs a deprecation', function(string $method, string $deprecated, string $replacement) {
    $deprecationLogs = fn() => array_filter(Deprecator::getRequestLogs(), fn($log) => $log->key === $deprecated);

    $replacementStatus = json($method, Url::actionUrl($replacement))->status();

    expect($replacementStatus)->not->toBeIn([404, 405])
        ->and($deprecationLogs())->toBeEmpty();

    $deprecatedStatus = json($method, Url::actionUrl($deprecated))->status();

    expect($deprecatedStatus)->toBe($replacementStatus)
        ->and($deprecationLogs())->toHaveCount(1)
        ->and(array_values($deprecationLogs())[0]->message)->toContain("Use `$replacement` instead.");
})->with([
    'get-cart' => ['GET', 'commerce/cart/get-cart', 'commerce/cart/show'],
    'update-cart' => ['POST', 'commerce/cart/update-cart', 'commerce/cart/update'],
    'load-cart' => ['POST', 'commerce/cart/load-cart', 'commerce/cart/load'],
    'forget-cart' => ['POST', 'commerce/cart/forget-cart', 'commerce/cart/forget'],
]);

it('still loads a cart from a recovery link issued for the deprecated load-cart action', function(string $action) {
    $fixture = CartsFixture::seed();

    $cart = new Order();
    $cart->number = app(Carts::class)->generateCartNumber();
    $cart->setCustomer($fixture->credentialedUser);
    Elements::saveElement($cart, false);

    getJson(Url::actionUrl($action, ['number' => $cart->number]))
        ->assertJsonPath('message', 'You must be logged in or provide a valid token to load this cart.');

    // Requests naming a cart number are limited to one per second.
    $this->travel(2)->seconds();

    $token = app(RouteTokens::class)->createToken(['commerce/cart/load-cart', ['cartNumber' => $cart->number]]);

    getJson(Url::actionUrl($action, ['number' => $cart->number, 'code' => $token]))
        ->assertOk()
        ->assertSessionHas('commerce:anonymousCartWithCredentialedCustomer:' . $cart->number, true);
})->with([
    'deprecated URL' => ['commerce/cart/load-cart'],
    'new URL' => ['commerce/cart/load'],
]);
