<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

/** @return array{order: array<string, mixed>} */
function ordersControllerPayload(Order $order, array $overrides = []): array
{
    return [
        'order' => array_merge([
            'id' => $order->id,
            'recalculationMode' => Order::RECALCULATION_MODE_ALL,
            'reference' => $order->reference,
            'customerId' => $order->getCustomerId(),
            'couponCode' => $order->couponCode,
            'isCompleted' => $order->isCompleted,
            'orderStatusId' => $order->orderStatusId,
            'orderSiteId' => $order->orderSiteId,
            'message' => $order->message,
            'shippingMethodHandle' => $order->shippingMethodHandle,
            'shippingMethodName' => $order->shippingMethodName,
            'notices' => [],
            'dateOrdered' => null,
            'lineItems' => [],
            'orderAdjustments' => [],
        ], $overrides),
    ];
}

it('lists purchasables for the order edit purchasable table', function() {
    OrdersFixture::seed();

    $response = get(Url::actionUrl('commerce/orders/purchasables-table', [
        'siteId' => Sites::getPrimarySite()->id,
    ]), ['Accept' => 'application/json'])->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
    expect($response->json('data'))->toHaveCount(2);

    $purchasable = collect($response->json('data'))->last();

    foreach (['id', 'price', 'priceAsCurrency', 'description', 'sku', 'isAvailable', 'detail'] as $key) {
        expect($purchasable)->toHaveKey($key);
    }

    expect($purchasable['sku'])->toBe('hct-blue');
});

it('sorts the purchasable table by the requested column', function() {
    OrdersFixture::seed();

    $response = get(Url::actionUrl('commerce/orders/purchasables-table', [
        'siteId' => Sites::getPrimarySite()->id,
        'sort' => 'sku|desc',
    ]), ['Accept' => 'application/json'])->assertOk();

    $purchasable = collect($response->json('data'))->last();

    expect($purchasable['sku'])->toBe('hct-blue');
});

it('searches for customers by email', function() {
    OrdersFixture::seed();

    $response = get(Url::actionUrl('commerce/orders/customer-search', [
        'query' => 'customer1',
    ]), ['Accept' => 'application/json'])->assertOk();

    $customers = $response->json('customers');
    expect($customers)->toHaveCount(1);

    foreach (['cpEditUrl', 'email', 'id', 'photo', 'status', 'totalAddresses'] as $key) {
        expect($customers[0])->toHaveKey($key);
    }

    expect($customers[0]['email'])->toBe('customer1@crafttest.com');
});

it('returns order counts per status for the index source badges', function() {
    $fixture = OrdersFixture::seed();

    $response = get(Url::actionUrl('commerce/orders/get-index-sources-badge-counts'), ['Accept' => 'application/json'])
        ->assertOk();

    $counts = $response->json('counts');

    expect($counts)->not->toBeEmpty();
    expect($response->json('total'))->toBe(count($fixture->orders));

    $firstCount = collect($counts)->first();
    foreach (['orderStatusId', 'handle', 'orderCount'] as $key) {
        expect($firstCount)->toHaveKey($key);
    }

    $shippedCount = collect($counts)->firstWhere('handle', 'shipped');
    expect($shippedCount['orderCount'])->toBe(1);
});

it('returns matching shipping method options for an order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $response = postJson(
        Url::actionUrl('commerce/orders/get-shipping-method-options'),
        ordersControllerPayload($order)
    )->assertOk();

    $options = $response->json('shippingMethodOptions');
    expect($options)->not->toBeEmpty();

    $option = reset($options);
    foreach (['handle', 'name', 'matchesOrder'] as $key) {
        expect($option)->toHaveKey($key);
    }
});

it('fails with a bad request when given an invalid order id', function() {
    postJson(Url::actionUrl('commerce/orders/get-shipping-method-options'), [
        'order' => ['id' => 999999],
    ])->assertStatus(400);
});

it('includes a custom runtime shipping method registered via the shipping methods event', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $customMethod = Mockery::mock(ShippingMethodInterface::class);
    $customMethod->shouldReceive('getId')->andReturn(null);
    $customMethod->shouldReceive('getName')->andReturn('My Custom Carrier');
    $customMethod->shouldReceive('getHandle')->andReturn('myCustomCarrier');
    $customMethod->shouldReceive('getIsEnabled')->andReturn(true);
    $customMethod->shouldReceive('getPriceForOrder')->andReturn(0.0);
    $customMethod->shouldReceive('matchOrder')->andReturn(true);

    Event::listen(RegisterAvailableShippingMethodsEvent::class, function(RegisterAvailableShippingMethodsEvent $event) use ($customMethod) {
        $event->setShippingMethods($event->getShippingMethods()->push($customMethod));
    });

    $response = postJson(
        Url::actionUrl('commerce/orders/get-shipping-method-options'),
        ordersControllerPayload($order)
    )->assertOk();

    $options = $response->json('shippingMethodOptions');

    expect($options)->toHaveKey('myCustomCarrier');
    expect($options['myCustomCarrier']['name'])->toBe('My Custom Carrier');
    expect($options['myCustomCarrier']['handle'])->toBe('myCustomCarrier');
});
