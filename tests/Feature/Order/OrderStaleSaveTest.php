<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Exceptions\OrderNumberConflictException;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;

/**
 * Simulates the interleaving of two requests that each hold their own instance of the same order.
 * A single PHP process can't run truly concurrent requests, so each test loads two instances, lets one
 * "request" save, then saves the other (now stale) instance, which is the problematic ordering.
 */
function staleSaveCart(): Order
{
    $fixture = OrdersFixture::seed();

    $order = new Order();
    $order->number = app(Carts::class)->generateCartNumber();
    $order->setCustomer(Users::ensureUserByEmail('customer1@crafttest.com'));
    $order->setLineItems([
        app(LineItems::class)->create($order, ['purchasableId' => $fixture->white->id, 'qty' => 1]),
    ]);
    expect(Elements::saveElement($order, false))->toBeTrue();

    return $order;
}

function staleSaveLoad(int $id): Order
{
    return Order::find()->id($id)->status(null)->withLineItems()->withAdjustments()->one();
}

function staleSaveAdjustment(Order $order, float $amount): OrderAdjustment
{
    $adjustment = new OrderAdjustment();
    $adjustment->type = 'discount';
    $adjustment->name = 'Test ' . $amount;
    $adjustment->amount = $amount;
    $adjustment->included = false;
    $adjustment->setOrder($order);

    return $adjustment;
}

function staleSaveRow(int $id): object
{
    return DB::table(Table::ORDERS)->where('id', $id)->first();
}

test('a stale incomplete copy cannot un-complete an order', function() {
    $cart = staleSaveCart();

    $stale = staleSaveLoad($cart->id);
    expect($stale->isCompleted)->toBeFalse();

    expect(staleSaveLoad($cart->id)->markAsComplete())->toBeTrue();
    $completedRow = staleSaveRow($cart->id);
    expect((bool) $completedRow->isCompleted)->toBeTrue();
    expect($completedRow->orderStatusId)->not->toBeNull();

    $stale->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
    Elements::saveElement($stale, false);

    $row = staleSaveRow($cart->id);
    expect((bool) $row->isCompleted)->toBeTrue();
    expect($row->orderStatusId)->toEqual($completedRow->orderStatusId);
    expect($row->reference)->toEqual($completedRow->reference);
});

test('a stale save replaces adjustments so the rows match the totals', function() {
    $cart = staleSaveCart();

    $seed = staleSaveLoad($cart->id);
    $seed->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
    $seed->setAdjustments([staleSaveAdjustment($seed, -1.0)]);
    expect(Elements::saveElement($seed, false))->toBeTrue();

    $stale = staleSaveLoad($cart->id);
    $staleAdjustmentId = $stale->getAdjustments()[0]->id;

    $live = staleSaveLoad($cart->id);
    $live->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
    $live->setAdjustments([staleSaveAdjustment($live, -2.0), staleSaveAdjustment($live, 7.5)]);
    expect(Elements::saveElement($live, false))->toBeTrue();
    $liveIds = array_map(fn(OrderAdjustment $adjustment) => $adjustment->id, $live->getAdjustments());
    expect($liveIds)->not->toContain($staleAdjustmentId);

    $stale->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
    expect(Elements::saveElement($stale, false))->toBeTrue();

    $rows = DB::table(Table::ORDERADJUSTMENTS)->where('orderId', $cart->id)->get();
    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]->amount)->toEqual(-1.0);
    expect($liveIds)->not->toContain($rows[0]->id);
});

test('a stale save does not leave the order without adjustments when all of them were removed', function() {
    $cart = staleSaveCart();

    $seed = staleSaveLoad($cart->id);
    $seed->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
    $seed->setAdjustments([staleSaveAdjustment($seed, -1.0)]);
    expect(Elements::saveElement($seed, false))->toBeTrue();

    $stale = staleSaveLoad($cart->id);

    DB::table(Table::ORDERADJUSTMENTS)->where('orderId', $cart->id)->delete();

    $stale->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
    expect(Elements::saveElement($stale, false))->toBeTrue();

    expect(DB::table(Table::ORDERADJUSTMENTS)->where('orderId', $cart->id)->count())->toBe(1);
});

test('saving a new order with an existing order number throws a conflict exception', function() {
    $cart = staleSaveCart();

    $duplicate = new Order();
    $duplicate->number = $cart->number;
    $duplicate->setCustomer(Users::ensureUserByEmail('customer1@crafttest.com'));

    expect(fn() => Elements::saveElement($duplicate, false))->toThrow(OrderNumberConflictException::class);
});
