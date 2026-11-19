<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\LineItemAdded;
use CraftCms\Commerce\Order\Events\LineItemAdding;
use CraftCms\Commerce\Order\Events\LineItemRemoved;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use Illuminate\Support\Facades\Event;

test('adding a line item dispatches LineItemAdding and LineItemAdded with the order', function() {
    $order = new Order();
    $lineItem = new LineItem();
    $dispatched = [];

    Event::listen(LineItemAdding::class, function(LineItemAdding $event) use (&$dispatched) {
        $dispatched[] = [LineItemAdding::class, $event->order, $event->lineItem];
    });
    Event::listen(LineItemAdded::class, function(LineItemAdded $event) use (&$dispatched) {
        $dispatched[] = [LineItemAdded::class, $event->order, $event->lineItem];
    });

    $order->addLineItem($lineItem);

    expect($dispatched)->toBe([
        [LineItemAdding::class, $order, $lineItem],
        [LineItemAdded::class, $order, $lineItem],
    ]);
});

test('a LineItemAdding listener can prevent a line item from being added', function() {
    $order = new Order();

    Event::listen(LineItemAdding::class, function(LineItemAdding $event) {
        $event->isValid = false;
    });

    $order->addLineItem(new LineItem());

    expect($order->getLineItems())->toBe([]);
});

test('removing a line item dispatches LineItemRemoved with the order', function() {
    $order = new Order();
    $lineItem = new LineItem();
    $order->addLineItem($lineItem);
    $removed = null;

    Event::listen(LineItemRemoved::class, function(LineItemRemoved $event) use (&$removed) {
        $removed = [$event->order, $event->lineItem];
    });

    $order->removeLineItem($lineItem);

    expect($removed)->toBe([$order, $lineItem]);
});
