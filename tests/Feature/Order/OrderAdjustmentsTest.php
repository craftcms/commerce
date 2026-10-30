<?php

declare(strict_types=1);

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderAdjustments;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;

function createOrderAdjustmentRow(Order $order): void
{
    DB::table(Table::ORDERADJUSTMENTS)->insert([
        'orderId' => $order->id,
        'type' => 'test',
        'name' => 'Test adjustment',
        'amount' => 1,
        'sourceSnapshot' => '{}',
        'dateCreated' => now()->toDateTimeString(),
        'dateUpdated' => now()->toDateTimeString(),
        'uid' => (string)Illuminate\Support\Str::uuid(),
    ]);
}

test('eager-loaded adjustments reuse their order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    createOrderAdjustmentRow($order);

    app(OrderAdjustments::class)->eagerLoadOrderAdjustmentsForOrders([$order]);

    expect($order->getAdjustments())->not->toBeEmpty()
        ->and($order->getAdjustments()[0]->getOrder())->toBe($order);
});

test('adjustments loaded by order ID reuse the given order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    createOrderAdjustmentRow($order);

    $order->setAdjustments([]);
    $adjustments = app(OrderAdjustments::class)->getAllOrderAdjustmentsByOrderId($order->id, $order);

    expect($adjustments)->not->toBeEmpty()
        ->and($adjustments[0]->getOrder())->toBe($order);
});
