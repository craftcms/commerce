<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent;
use CraftCms\Commerce\Shipping\ShippingMethods;
use Illuminate\Support\Facades\Event;

function mockShippingMethod(string $name, string $handle, float $price): ShippingMethodInterface
{
    $method = Mockery::mock(ShippingMethodInterface::class);
    $method->shouldReceive('getHandle')->andReturn($handle);
    $method->shouldReceive('getName')->andReturn($name);
    $method->shouldReceive('getPriceForOrder')->andReturn($price);
    $method->shouldReceive('getIsEnabled')->andReturn(true);
    $method->shouldReceive('matchOrder')->andReturn(true);

    return $method;
}

test('getMatchingShippingMethods sorts matching methods by price', function() {
    $order = new Order();

    Event::listen(RegisterAvailableShippingMethodsEvent::class, function(RegisterAvailableShippingMethodsEvent $event) {
        $shippingMethods = $event->getShippingMethods();

        $shippingMethods->push(mockShippingMethod('First', 'first', 12.34));
        $shippingMethods->push(mockShippingMethod('Second', 'second', 12.35));
        $shippingMethods->push(mockShippingMethod('Really First', 'reallyFirst', 12.33));
    });

    $matchingMethods = app(ShippingMethods::class)->getMatchingShippingMethods($order);

    expect(array_keys($matchingMethods))->toBe(['reallyFirst', 'first', 'second']);
});
