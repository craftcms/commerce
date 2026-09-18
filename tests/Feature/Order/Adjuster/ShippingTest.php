<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Adjuster\Shipping;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent;
use Illuminate\Support\Facades\Event;

test('adjuster drops the shipping adjustment cleanly when a previously-matching third-party method stops matching', function() {
    // Simulates a third-party plugin registering a shipping method that re-evaluates live
    // availability on every call instead of returning a static result, so the second
    // `adjust()` call below can stop matching mid-test.
    $thirdPartyMethodMatches = true;

    $method = Mockery::mock(ShippingMethodInterface::class);
    $method->shouldReceive('getId')->andReturn(null);
    $method->shouldReceive('getType')->andReturn('Third Party');
    $method->shouldReceive('getName')->andReturn('Third Party Flat Rate');
    $method->shouldReceive('getHandle')->andReturn('thirdPartyFlatRate');
    $method->shouldReceive('getIsEnabled')->andReturn(true);
    $method->shouldReceive('getMatchingShippingRule')->andReturn(null);
    $method->shouldReceive('getPriceForOrder')->andReturn(8.99);
    $method->shouldReceive('matchOrder')->andReturnUsing(function() use (&$thirdPartyMethodMatches) {
        return $thirdPartyMethodMatches;
    });

    Event::listen(RegisterAvailableShippingMethodsEvent::class, function(RegisterAvailableShippingMethodsEvent $event) use ($method) {
        $event->setShippingMethods($event->getShippingMethods()->push($method));
    });

    $lineItem = new LineItem();
    $lineItem->qty = 1;
    $lineItem->setPrice(50);
    $lineItem->setIsShippable(true);

    $order = new Order();
    $order->shippingMethodHandle = 'thirdPartyFlatRate';
    $order->setLineItems([$lineItem]);

    $adjuster = new Shipping();

    $firstPass = $adjuster->adjust($order);
    expect($firstPass)->toHaveCount(1);
    expect($firstPass[0]->amount)->toEqual(8.99);

    $thirdPartyMethodMatches = false;

    $secondPass = $adjuster->adjust($order);
    expect($secondPass)->toBe([]);
});
