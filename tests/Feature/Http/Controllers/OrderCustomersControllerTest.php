<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function customersSearchUrl(string $query): string
{
    return Url::actionUrl('commerce/orders/customers/search', ['query' => $query]);
}

it('finds customers by the start of a word in the user search index', function() {
    $fixture = OrdersFixture::seed();

    getJson(customersSearchUrl('customer1'))
        ->assertOk()
        ->assertJsonPath('customers.0.id', $fixture->customer->id)
        ->assertJsonPath('customers.0.email', 'customer1@crafttest.com');

    getJson(customersSearchUrl('custom'))
        ->assertOk()
        ->assertJsonPath('customers.0.id', $fixture->customer->id);
});

it('returns nothing for a query that is too short to search', function() {
    OrdersFixture::seed();

    getJson(customersSearchUrl('c'))
        ->assertOk()
        ->assertJsonPath('customers', []);
});

it('only returns customers the current user may view', function() {
    OrdersFixture::seed();
    Gate::before(fn($user, $ability, $arguments) => $ability === 'view' && ($arguments[0] ?? null) instanceof User ? false : null);

    getJson(customersSearchUrl('customer1'))
        ->assertOk()
        ->assertJsonPath('customers', []);
});

it('tells the search whether the current user can create customers', function() {
    getJson(customersSearchUrl('nobody'))->assertOk()->assertJsonPath('canCreate', true);

    Gate::before(fn($user, $ability, $arguments) => $ability === 'save' && ($arguments[0] ?? null) instanceof User ? false : null);

    getJson(customersSearchUrl('nobody'))->assertOk()->assertJsonPath('canCreate', false);
});

it('creates a customer from an email address, and returns the same one next time', function() {
    $first = postJson(Url::actionUrl('commerce/orders/customers/create'), ['email' => 'new-customer@crafttest.com'])
        ->assertOk()
        ->assertJsonPath('customer.email', 'new-customer@crafttest.com')
        ->assertJsonPath('message', 'Customer created.');

    postJson(Url::actionUrl('commerce/orders/customers/create'), ['email' => 'new-customer@crafttest.com'])
        ->assertOk()
        ->assertJsonPath('customer.id', $first->json('customer.id'));

    expect(User::find()->email('new-customer@crafttest.com')->status(null)->count())->toBe(1);
});

it('refuses to create a customer from something that isn’t an email address', function() {
    postJson(Url::actionUrl('commerce/orders/customers/create'), ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('refuses to create a customer without the permission to create users', function() {
    Gate::before(fn($user, $ability, $arguments) => $ability === 'save' && ($arguments[0] ?? null) instanceof User ? false : null);

    postJson(Url::actionUrl('commerce/orders/customers/create'), ['email' => 'blocked@crafttest.com'])->assertForbidden();

    expect(User::find()->email('blocked@crafttest.com')->status(null)->exists())->toBeFalse();
});

it('lets a cart lose its customer but not a completed order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'setCustomer', 'customerId' => null],
    ])->assertStatus(422)->assertJsonPath('message', 'A completed order needs a customer.');

    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    $cart->setCustomerId($fixture->customer->id);
    Elements::saveElement($cart, false);
    $cart = Order::find()->id($cart->id)->isCompleted(false)->one();

    postJson(Url::actionUrl('commerce/orders/apply'), [
        'orderId' => $cart->id,
        'dateUpdated' => $cart->dateUpdated->format(DATE_ATOM),
        'operations' => [['type' => 'setCustomer', 'customerId' => null]],
    ])->assertOk();

    expect(Order::find()->id($cart->id)->isCompleted(false)->one()->getCustomerId())->toBeNull();
});
