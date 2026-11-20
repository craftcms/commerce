<?php

declare(strict_types=1);

use CraftCms\Cms\Activity\Data\ActivitySubject;
use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Order\Activity\OrderEdited;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\OrderFulfillment;
use CraftCms\Commerce\Order\OrderStockReconciliation;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

/**
 * @return array{order: Order, fixture: OrdersFixture}
 */
function stockOrder(int $qty = 3, int $stock = 10): array
{
    $fixture = OrdersFixture::seed();

    foreach ([$fixture->white, $fixture->blue] as $variant) {
        $variant->inventoryTracked = true;
        Elements::saveElement($variant, false);
        app(Inventory::class)->updatePurchasableInventoryLevel($variant, $stock);
    }

    $order = new Order();
    $order->number = bin2hex(random_bytes(16));
    $order->storeId = $fixture->storeId;
    $order->setCustomerId($fixture->customer->id);
    Elements::saveElement($order, false);
    $order->setLineItems([app(LineItems::class)->create($order, ['purchasableId' => $fixture->white->id, 'qty' => $qty])]);
    Elements::saveElement($order, false);
    $order->markAsComplete();

    return ['order' => Order::find()->id($order->id)->one(), 'fixture' => $fixture];
}

/** @param list<array<string, mixed>> $operations */
function stockCommit(Order $order, array $operations): bool
{
    return app(OrderOperations::class)->commit($order, app(OrderOperations::class)->editFromArray([
        'version' => OrderEdit::VERSION,
        'operations' => $operations,
    ]));
}

function stockLevel(Order $order, Variant $variant): ?InventoryFulfillmentLevel
{
    $lineItem = collect(Order::find()->id($order->id)->one()->getLineItems())->first(fn($item) => $item->purchasableId === $variant->id);

    return $lineItem ? app(Inventory::class)->getInventoryFulfillmentLevels($order)->first(fn($level) => $level->lineItemId === $lineItem->id) : null;
}

function stockAvailable(Variant $variant): int
{
    return (int)app(Inventory::class)->getInventoryLevelsForPurchasable(Elements::getElementById($variant->id))->sum('availableTotal');
}

it('commits more stock when a completed order’s quantity goes up', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(3);
    expect(stockAvailable($fixture->white))->toBe(7);

    stockCommit($order, [['type' => 'updateLineItem', 'lineItemUid' => $order->getLineItems()[0]->uid, 'changes' => ['qty' => 5]]]);

    expect(stockLevel($order, $fixture->white)->committedQuantity)->toBe(5)
        ->and(stockLevel($order, $fixture->white)->outstandingCommittedQuantity)->toBe(5)
        ->and(stockAvailable($fixture->white))->toBe(5);
});

it('commits stock for a product added to a completed order', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(3);

    stockCommit($order, [['type' => 'addLineItem', 'uid' => Str::uuid()->toString(), 'purchasableId' => $fixture->blue->id, 'qty' => 2]]);

    expect(stockLevel($order, $fixture->blue)->outstandingCommittedQuantity)->toBe(2)
        ->and(stockAvailable($fixture->blue))->toBe(8);
});

it('releases committed stock when the quantity goes down', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(3);

    stockCommit($order, [['type' => 'updateLineItem', 'lineItemUid' => $order->getLineItems()[0]->uid, 'changes' => ['qty' => 1]]]);

    expect(stockLevel($order, $fixture->white)->outstandingCommittedQuantity)->toBe(1)
        ->and(stockAvailable($fixture->white))->toBe(9);
});

it('releases only what has not been fulfilled and stops the quantity going below what has', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(4);
    $level = stockLevel($order, $fixture->white);
    app(OrderFulfillment::class)->fulfill([[
        'inventoryLocationId' => $level->inventoryLocationId,
        'inventoryItemId' => $level->inventoryItemId,
        'lineItemId' => $level->lineItemId,
        'quantity' => 2,
    ]]);
    $uid = $order->getLineItems()[0]->uid;

    expect(fn() => stockCommit(Order::find()->id($order->id)->one(), [['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['qty' => 1]]]))
        ->toThrow(OperationNotApplicableException::class);

    stockCommit(Order::find()->id($order->id)->one(), [['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['qty' => 2]]]);

    $level = stockLevel($order, $fixture->white);
    expect($level->fulfilledQuantity)->toBe(2)
        ->and($level->outstandingCommittedQuantity)->toBe(0);
});

it('stops a line item with fulfilled stock being removed', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(2);
    $level = stockLevel($order, $fixture->white);
    app(OrderFulfillment::class)->fulfill([[
        'inventoryLocationId' => $level->inventoryLocationId,
        'inventoryItemId' => $level->inventoryItemId,
        'lineItemId' => $level->lineItemId,
        'quantity' => 1,
    ]]);

    expect(fn() => stockCommit(Order::find()->id($order->id)->one(), [['type' => 'removeLineItem', 'lineItemUid' => $order->getLineItems()[0]->uid]]))
        ->toThrow(OperationNotApplicableException::class);
});

it('gives the stock back when a line item with nothing fulfilled is removed', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(3);
    $uid = $order->getLineItems()[0]->uid;

    stockCommit($order, [
        ['type' => 'addLineItem', 'uid' => Str::uuid()->toString(), 'purchasableId' => $fixture->blue->id, 'qty' => 1],
        ['type' => 'removeLineItem', 'lineItemUid' => $uid],
    ]);

    expect(stockAvailable($fixture->white))->toBe(10)
        ->and(app(Inventory::class)->getInventoryFulfillmentLevels($order)->where('inventoryItemId', $fixture->white->inventoryItemId))->toBeEmpty();
});

it('leaves stock alone for edits that don’t change quantities', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(3);

    stockCommit($order, [['type' => 'setReference', 'reference' => 'PO-1']]);

    expect(stockAvailable($fixture->white))->toBe(7);
});

it('describes the stock an edit commits and releases, and what is short', function() {
    ['order' => $order, 'fixture' => $fixture] = stockOrder(3, 4);
    $before = Order::find()->id($order->id)->one();
    $after = Order::find()->id($order->id)->one();
    $after->getLineItems()[0]->qty = 8;

    $changes = app(OrderStockReconciliation::class)->changes($before, $after);

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['quantity'])->toBe(5)
        ->and($changes[0]['short'])->toBe(4);

    $after->getLineItems()[0]->qty = 1;

    expect(app(OrderStockReconciliation::class)->changes($before, $after)[0])->toMatchArray(['quantity' => -2, 'short' => 0]);
});

it('records the stock changes in the order’s history', function() {
    ['order' => $order] = stockOrder(3);

    stockCommit($order, [['type' => 'updateLineItem', 'lineItemUid' => $order->getLineItems()[0]->uid, 'changes' => ['qty' => 5]]]);

    $events = Activities::query()->subject(ActivitySubject::fromElement($order))->where('eventType', OrderEdited::class)->get();

    expect(json_encode($events->first()->data))->toContain('Committed 2');
});
