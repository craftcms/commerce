<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;

/**
 * Registers a shipping method that re-evaluates whether it matches the order on every call
 * via the given closure, rather than a static, persisted method — lets a test flip a method
 * from matching to not matching partway through.
 */
function registerDynamicShippingMethod(Closure $matches): void
{
    Event::listen(RegisterAvailableShippingMethodsEvent::class, function(RegisterAvailableShippingMethodsEvent $event) use ($matches) {
        $method = Mockery::mock(ShippingMethodInterface::class);
        $method->shouldReceive('getId')->andReturn(null);
        $method->shouldReceive('getType')->andReturn('dynamic');
        $method->shouldReceive('getHandle')->andReturn('dynamicFlatRate');
        $method->shouldReceive('getName')->andReturn('Dynamic Flat Rate');
        $method->shouldReceive('getIsEnabled')->andReturn(true);
        $method->shouldReceive('getPriceForOrder')->andReturn(8.99);
        $method->shouldReceive('getMatchingShippingRule')->andReturn(null);
        $method->shouldReceive('matchOrder')->andReturnUsing($matches);

        $event->getShippingMethods()->push($method);
    });
}

/**
 * Stubs a resolvable gateway for the given order, so `Transactions::createTransaction()` can
 * build a transaction against it without a real payment gateway configured in the store.
 */
function stubGatewayForRecalculationOrder(Order $order): void
{
    $gateway = app(Gateways::class)->createGateway([
        'type' => Dummy::class,
        'name' => 'Dummy',
        'handle' => 'dummy',
        'isFrontendEnabled' => true,
    ]);
    $gateway->id = 1;
    $order->gatewayId = $gateway->id;

    $mock = Mockery::mock(Gateways::class)->makePartial();
    $mock->shouldReceive('getGatewayById')->andReturn($gateway);
    app()->instance(Gateways::class, $mock);
}

/**
 * Builds an unsaved cart with a single line item against the fixture's white variant, saved
 * once so it has an ID (`recalculate()` requires a saved order).
 */
function cartWithLineItem(OrdersFixture $fixture): Order
{
    $order = new Order();
    Elements::saveElement($order, false);

    $lineItem = app(LineItems::class)->create($order, [
        'purchasableId' => $fixture->white->id,
        'qty' => 1,
    ]);
    $order->setLineItems([$lineItem]);

    return $order;
}

test('a completed and paid order stays locked against recalculation, even after its shipping method stops matching', function() {
    $fixture = OrdersFixture::seed();

    $matches = true;
    registerDynamicShippingMethod(function() use (&$matches) {
        return $matches;
    });

    $order = cartWithLineItem($fixture);
    $order->shippingMethodHandle = 'dynamicFlatRate';
    stubGatewayForRecalculationOrder($order);

    // Cart is untouched, so recalculation mode defaults to `ALL`, as throughout a normal checkout.
    expect($order->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_ALL);

    // Checkout: the method matches, its cost gets applied and persisted.
    $order->recalculate();
    Elements::saveElement($order, false);

    $totalCollected = $order->getTotalPrice();
    expect($order->getTotalShippingCost())->toBeGreaterThan(0.0);

    // Customer pays the full amount shown at checkout, including shipping.
    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $transaction->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($transaction);

    // The order is now completed and paid in full...
    expect($order->isCompleted)->toBeTrue();
    expect($order->hasOutstandingBalance())->toBeFalse();
    expect($order->getTotalPaid())->toBe($totalCollected);

    // ...and stays locked at `NONE` rather than being restored to the `ALL` mode it had as a cart.
    expect($order->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);

    // Some time later — a queue job, webhook, or fulfillment plugin — something recalculates
    // this completed order again, and this time the shipping method fails to match. Doesn't
    // matter now, since recalculation is locked out.
    $matches = false;
    $order->recalculate();

    // Nothing changed: still completed, still paid, shipping cost and handle untouched, no
    // "shippingMethodChanged" notice.
    expect($order->isCompleted)->toBeTrue();
    expect($order->getTotalPrice())->toBe($totalCollected);
    expect($order->getTotalPaid())->toBe($totalCollected);
    expect($order->shippingMethodHandle)->toBe('dynamicFlatRate');
    expect($order->hasNotices('shippingMethodChanged'))->toBeFalse();
});

test('manually unlocking recalculation mode on a completed order lets it drop a shipping cost that no longer matches', function() {
    $fixture = OrdersFixture::seed();

    $matches = true;
    registerDynamicShippingMethod(function() use (&$matches) {
        return $matches;
    });

    $order = cartWithLineItem($fixture);
    $order->shippingMethodHandle = 'dynamicFlatRate';
    stubGatewayForRecalculationOrder($order);

    $order->recalculate();
    Elements::saveElement($order, false);

    $totalCollected = $order->getTotalPrice();
    expect($order->getTotalShippingCost())->toBeGreaterThan(0.0);

    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $transaction->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($transaction);

    expect($order->isCompleted)->toBeTrue();
    expect($order->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);

    // Manually unlock the completed, paid order back to `ALL`.
    $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);

    // The shipping method stops matching, then something saves the order — `afterSave()`
    // unconditionally calls `recalculate()`, which now actually runs, since mode is `ALL` again.
    $matches = false;
    Elements::saveElement($order, false);

    // The shipping cost disappeared, even though the order is still marked completed and paid.
    expect($order->isCompleted)->toBeTrue();
    expect($order->getTotalShippingCost())->toBe(0.0);
    expect($order->getTotalPrice())->toBeLessThan($totalCollected);
    expect($order->getTotalPaid())->toBeGreaterThan($order->getTotalPrice());
});

test('a cart that receives a payment update without completing stays fully recalculable', function() {
    $fixture = OrdersFixture::seed();

    $order = cartWithLineItem($fixture);
    $order->recalculate();
    Elements::saveElement($order, false);

    expect($order->hasOutstandingBalance())->toBeTrue();
    expect($order->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_ALL);

    // Nothing paid or authorized, so this can't complete the order, but it still exercises the
    // same lock/restore logic that `updateOrderPaidInformation()` runs on every payment update.
    $order->updateOrderPaidInformation();

    expect($order->isCompleted)->toBeFalse();
    expect($order->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_ALL);
});

test('saving an already-completed, already-paid order again has no adverse effect on its totals', function() {
    $fixture = OrdersFixture::seed();

    $matches = true;
    registerDynamicShippingMethod(function() use (&$matches) {
        return $matches;
    });

    $order = cartWithLineItem($fixture);
    $order->shippingMethodHandle = 'dynamicFlatRate';
    stubGatewayForRecalculationOrder($order);

    $order->recalculate();
    Elements::saveElement($order, false);

    $totalCollected = $order->getTotalPrice();
    $shippingCost = $order->getTotalShippingCost();
    expect($shippingCost)->toBeGreaterThan(0.0);

    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $transaction->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($transaction);

    expect($order->isCompleted)->toBeTrue();

    // The shipping method stops matching some time later — it doesn't matter, because
    // recalculation is locked out.
    $matches = false;

    // Custom code saves the already-completed, already-paid order again, for reasons unrelated
    // to shipping/adjustments.
    Elements::saveElement($order, false);

    expect($order->isCompleted)->toBeTrue();
    expect($order->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);
    expect($order->getTotalShippingCost())->toBe($shippingCost);
    expect($order->getTotalPrice())->toBe($totalCollected);
    expect($order->getTotalPaid())->toBe($totalCollected);
    expect($order->hasOutstandingBalance())->toBeFalse();
});
