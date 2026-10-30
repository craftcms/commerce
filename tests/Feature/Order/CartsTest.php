<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\CartsFixture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Facade;

/**
 * Pins the given Carts instance to a specific cart number, as if that number had already been
 * read from the request's cart cookie. setSessionCartNumber() can't be used for this: outside a
 * real web request it only stores the number when app()->runningInConsole() is false, and the
 * PHP CLI process running this test suite always reports true there. Setting the private state
 * directly sidesteps that guard so a directly-inserted cart row can be looked up by number.
 */
function primeCartSession(Carts $carts, string $cartNumber): void
{
    $property = new ReflectionProperty($carts, 'cartNumber');
    $property->setValue($carts, $cartNumber);
}

test('getCart auto-sets billing/shipping addresses only for a logged-in customer when the store enables it', function(string $userKey, bool $autoSet, bool $hasBillingAddress, bool $hasShippingAddress, bool $loggedIn) {
    $fixture = CartsFixture::seed();
    $user = $fixture->{$userKey};

    app(Stores::class)->getCurrentStore()->setAutoSetNewCartAddresses($autoSet);

    if ($loggedIn) {
        $this->actingAs($user, 'craft');
    }

    $carts = app(Carts::class);
    $cartNumber = $carts->generateCartNumber();
    primeCartSession($carts, $cartNumber);

    $cart = new Order();
    $cart->number = $cartNumber;
    $cart->setCustomer($user);
    Elements::saveElement($cart, false);

    $result = $carts->getCart();

    expect($result->getBillingAddress() !== null)->toBe($hasBillingAddress);
    expect($result->getShippingAddress() !== null)->toBe($hasShippingAddress);
})->with([
    'anonymous, auto-set disabled' => ['inactiveUser', false, false, false, false],
    'anonymous, auto-set enabled but not logged in' => ['inactiveUser', true, false, false, false],
    'logged in, auto-set disabled' => ['credentialedUser', false, false, false, true],
    'logged in, auto-set enabled' => ['credentialedUser', true, true, true, true],
]);

test('getCart switches an anonymous session cart to the logged-in customer', function() {
    $fixture = CartsFixture::seed();
    $this->actingAs($fixture->credentialedUser, 'craft');

    $carts = app(Carts::class);
    $cartNumber = $carts->generateCartNumber();
    primeCartSession($carts, $cartNumber);

    $order = new Order();
    $order->number = $cartNumber;
    $order->setCustomer($fixture->inactiveUser);
    Elements::saveElement($order, false);
    expect($order->getCustomerId())->toBe($fixture->inactiveUser->id);

    $cart = $carts->getCart();

    expect($cart->number)->toBe($cartNumber);
    expect($cart->getCustomerId())->toBe($fixture->credentialedUser->id);
    expect($cart->getEmail())->toBe($fixture->credentialedUser->email);
});

test('getCart forgets a credentialed customer\'s cart for an anonymous visitor without prior authorization', function() {
    // A credentialed customer's cart is private — an anonymous visitor should never be served it
    // unless the session was explicitly authorized (see the next two tests).
    // @see https://github.com/craftcms/commerce/issues/4225
    $fixture = CartsFixture::seed();

    $carts = app(Carts::class);
    $cartNumber = $carts->generateCartNumber();
    primeCartSession($carts, $cartNumber);

    $order = new Order();
    $order->number = $cartNumber;
    $order->setCustomer($fixture->credentialedUser);
    Elements::saveElement($order, false);

    $cart = $carts->getCart();

    expect($cart->number)->not->toBe($cartNumber);
    expect($cart->getCustomerId())->toBeNull();
});

test('getCart serves a credentialed customer\'s cart to an anonymous visitor once the session is authorized', function() {
    // @see https://github.com/craftcms/commerce/issues/4225
    $fixture = CartsFixture::seed();

    $carts = app(Carts::class);
    $cartNumber = $carts->generateCartNumber();
    primeCartSession($carts, $cartNumber);

    $order = new Order();
    $order->number = $cartNumber;
    $order->setCustomer($fixture->credentialedUser);
    Elements::saveElement($order, false);

    // Mirrors what CartController::actionLoadCart() does after validating a load-cart token.
    session()->put('commerce:anonymousCartWithCredentialedCustomer:' . $cartNumber, true);

    $cart = $carts->getCart();

    expect($cart->number)->toBe($cartNumber);
    expect($cart->getCustomerId())->toBe($fixture->credentialedUser->id);
});

test('getCart lets a different logged-in user acquire an authorized credentialed customer\'s cart', function() {
    // @see https://github.com/craftcms/commerce/issues/4225
    $fixture = CartsFixture::seed();
    $this->actingAs($fixture->loadingUser, 'craft');

    $carts = app(Carts::class);
    $cartNumber = $carts->generateCartNumber();
    primeCartSession($carts, $cartNumber);

    $order = new Order();
    $order->number = $cartNumber;
    $order->setCustomer($fixture->credentialedUser);
    Elements::saveElement($order, false);
    expect($order->getCustomerId())->toBe($fixture->credentialedUser->id);

    session()->put('commerce:anonymousCartWithCredentialedCustomer:' . $cartNumber, true);

    $cart = $carts->getCart();

    expect($cart->number)->toBe($cartNumber);
    expect($cart->getCustomerId())->toBe($fixture->loadingUser->id);
    expect($cart->getEmail())->toBe($fixture->loadingUser->email);
});

test('forgetCart followed by getCart returns a cart with a new number', function() {
    $carts = app(Carts::class);

    $initialCart = $carts->getCart();
    $originalNumber = $initialCart->number;

    $carts->forgetCart();
    $newCart = $carts->getCart();

    expect($newCart->number)->not->toBe($originalNumber);
});

test('forgetCart prevents a stale cart cookie from restoring the forgotten cart', function() {
    // @see https://github.com/craftcms/commerce/issues/4279
    $carts = app(Carts::class);
    $cookieName = $carts->cartCookie['name'];

    $initialCart = $carts->getCart();
    $originalNumber = $initialCart->number;

    $carts->forgetCart();

    // The test process runs as CLI, so app()->runningInConsole() is true and Carts skips its
    // cookie handling entirely. Force it false so getCart() takes the same cookie-reading branch
    // a real web request would, otherwise this couldn't catch a regression here.
    $runningInConsole = new ReflectionProperty(app(), 'isRunningInConsole');
    $originalRunningInConsole = $runningInConsole->getValue(app());
    $runningInConsole->setValue(app(), false);

    // Simulate a browser that still carries the Set-Cookie value issued before forgetCart().
    $request = Request::create('/', 'GET', [], [$cookieName => $originalNumber]);
    app()->instance('request', $request);
    Facade::clearResolvedInstance('request');

    try {
        $cart = $carts->getCart();

        expect($cart->number)->not->toBe($originalNumber);
    } finally {
        $runningInConsole->setValue(app(), $originalRunningInConsole);
    }
});

test('peekCart returns the cart matching the cart cookie without starting a new cart session', function() {
    $cartNumber = app(Carts::class)->generateCartNumber();
    $order = new Order();
    $order->number = $cartNumber;
    Elements::saveElement($order, false);

    $cookieName = app(Carts::class)->cartCookie['name'];

    // Force a real web-request context so a wrongly-queued cart cookie would actually show up
    // below — see the note in the previous test.
    $runningInConsole = new ReflectionProperty(app(), 'isRunningInConsole');
    $originalRunningInConsole = $runningInConsole->getValue(app());
    $runningInConsole->setValue(app(), false);

    $request = Request::create('/', 'GET', [], [$cookieName => $cartNumber]);
    app()->instance('request', $request);
    Facade::clearResolvedInstance('request');

    try {
        $cart = app(Carts::class)->peekCart();

        expect($cart)->not->toBeNull();
        expect($cart->number)->toBe($cartNumber);
        // Only setSessionCartNumber() queues the Set-Cookie header that "starts" a cart session —
        // peekCart() must never call it.
        expect(Cookie::hasQueued($cookieName))->toBeFalse();
    } finally {
        $runningInConsole->setValue(app(), $originalRunningInConsole);
    }
});

test('peekCart returns null when there is no cart cookie', function() {
    $request = Request::create('/');
    app()->instance('request', $request);
    Facade::clearResolvedInstance('request');

    expect(app(Carts::class)->peekCart())->toBeNull();
});
