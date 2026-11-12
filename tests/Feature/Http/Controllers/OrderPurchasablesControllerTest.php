<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Events\ModifyPurchasablesTableQueryEvent;
use CraftCms\Commerce\Purchasable\Events\PurchasableAvailabilityResolving;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function searchPurchasables(OrdersFixture $fixture, array $params = []): Illuminate\Testing\TestResponse
{
    return getJson(Url::actionUrl('commerce/orders/purchasables/search', [
        'orderId' => $fixture->orders['completed-new']->id,
        ...$params,
    ]));
}

it('lists the purchasables of every registered type for an order', function() {
    $fixture = OrdersFixture::seed();

    $response = searchPurchasables($fixture)->assertOk();

    expect($response->json('total'))->toBe(2);
    expect($response->json('priceSortedBy'))->toBe('base');
    expect($response->json('types'))->toBe([['type' => Variant::class, 'label' => Variant::displayName()]]);

    $purchasable = collect($response->json('data'))->firstWhere('sku', 'hct-blue');

    expect($purchasable)->toMatchArray([
        'id' => $fixture->blue->id,
        'type' => Variant::class,
        'typeLabel' => Variant::displayName(),
        'isAvailable' => true,
        'unavailableReason' => null,
        'stock' => null,
    ]);
    expect($purchasable['price'])->toBe(21.99);
    expect($purchasable['priceAsCurrency'])->toBe('$21.99');
});

it('searches descriptions and partial SKUs', function() {
    $fixture = OrdersFixture::seed();

    expect(collect(searchPurchasables($fixture, ['search' => 'blue'])->assertOk()->json('data'))->pluck('sku')->all())->toBe(['hct-blue']);
    expect(searchPurchasables($fixture, ['search' => 'hct-w'])->assertOk()->json('total'))->toBe(1);
    expect(searchPurchasables($fixture, ['search' => 'nothing like this'])->assertOk()->json('data'))->toBe([]);
});

it('filters by purchasable type and ignores an unknown one', function() {
    $fixture = OrdersFixture::seed();

    expect(searchPurchasables($fixture, ['type' => Variant::class])->assertOk()->json('total'))->toBe(2);
    expect(searchPurchasables($fixture, ['type' => 'Not\\A\\Type'])->assertOk()->json('total'))->toBe(2);
});

it('sorts and pages the purchasables', function() {
    $fixture = OrdersFixture::seed();

    $skus = fn(array $params) => collect(searchPurchasables($fixture, $params)->assertOk()->json('data'))->pluck('sku')->all();

    expect($skus(['sort' => 'sku', 'direction' => 'desc']))->toBe(['hct-white', 'hct-blue']);
    expect($skus(['sort' => 'price', 'direction' => 'asc']))->toBe(['hct-white', 'hct-blue']);
    expect($skus(['sort' => 'price', 'direction' => 'desc']))->toBe(['hct-blue', 'hct-white']);
    expect($skus(['sort' => 'sku', 'perPage' => 1, 'page' => 2]))->toBe(['hct-white']);
    expect(searchPurchasables($fixture, ['perPage' => 1])->json('total'))->toBe(2);
});

it('says why a purchasable is unavailable to the order', function() {
    $fixture = OrdersFixture::seed();

    Event::listen(PurchasableAvailabilityResolving::class, function(PurchasableAvailabilityResolving $event) use ($fixture) {
        if ($event->purchasable->id === $fixture->blue->id) {
            $event->isAvailable = false;
        }
    });

    $rows = collect(searchPurchasables($fixture)->assertOk()->json('data'))->keyBy('sku');

    expect($rows['hct-blue']['isAvailable'])->toBeFalse();
    expect($rows['hct-blue']['unavailableReason'])->toBe('Not available.');
    expect($rows['hct-white']['isAvailable'])->toBeTrue();
});

it('lets a listener change the query', function() {
    $fixture = OrdersFixture::seed();

    Event::listen(ModifyPurchasablesTableQueryEvent::class, function(ModifyPurchasablesTableQueryEvent $event) {
        $event->query->where('purchasables.sku', 'hct-blue');
    });

    $response = searchPurchasables($fixture)->assertOk();

    expect($response->json('total'))->toBe(1);
    expect(collect($response->json('data'))->pluck('sku')->all())->toBe(['hct-blue']);
});

it('requires an order the user can edit', function() {
    $fixture = OrdersFixture::seed();

    getJson(Url::actionUrl('commerce/orders/purchasables/search', ['orderId' => 0]))->assertNotFound();
    getJson(Url::actionUrl('commerce/orders/purchasables/search'))->assertUnprocessable();
    expect($fixture->orders['completed-new']->id)->toBeInt();
});
