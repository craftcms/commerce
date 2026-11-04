<?php

declare(strict_types=1);

use craft\commerce\events\AddLineItemEvent;
use craft\commerce\events\LineItemEvent as LegacyLineItemEvent;
use craft\commerce\events\OrderLineItemsRefreshEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\LineItemAdded;
use CraftCms\Commerce\Order\Events\LineItemAdding;
use CraftCms\Commerce\Order\Events\LineItemRemoved;
use CraftCms\Commerce\Order\Events\LineItemsRefreshing;
use CraftCms\Commerce\Order\Events\OrderCompleted;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use Illuminate\Support\Facades\Event;
use yii\base\Event as YiiEvent;

afterEach(function() {
    if (class_exists(YiiEvent::class)) {
        YiiEvent::offAll();
    }
});

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

test('legacy listeners registered on the craft\commerce\elements\Order alias receive order events', function() {
    $order = new Order();
    $received = null;

    YiiEvent::on('craft\commerce\elements\Order', Order::EVENT_AFTER_COMPLETE_ORDER, function(YiiEvent $event) use (&$received) {
        $received = $event->sender;
    });

    event(new OrderCompleted($order));

    expect($received)->toBe($order);
})->group('yii2-adapter');

test('legacy listeners registered on the order instance and on the new class receive order events', function() {
    $order = new Order();
    $received = [];

    $order->on(Order::EVENT_AFTER_ADD_LINE_ITEM, function(LegacyLineItemEvent $event) use (&$received) {
        $received[] = 'instance';
    });
    YiiEvent::on(Order::class, Order::EVENT_AFTER_ADD_LINE_ITEM, function(LegacyLineItemEvent $event) use (&$received, $order) {
        $received[] = $event->sender === $order ? 'class' : 'wrong sender';
    });

    $order->addLineItem(new LineItem());

    expect($received)->toBe(['instance', 'class']);
})->group('yii2-adapter');

test('a legacy listener can prevent a line item from being added', function() {
    $order = new Order();

    YiiEvent::on('craft\commerce\elements\Order', Order::EVENT_BEFORE_ADD_LINE_ITEM, function(AddLineItemEvent $event) {
        $event->isValid = false;
    });

    $order->addLineItem(new LineItem());

    expect($order->getLineItems())->toBe([]);
})->group('yii2-adapter');

test('legacy line item refresh listeners can change the line items and request a recalculation', function() {
    $order = new Order();
    $lineItem = new LineItem();

    YiiEvent::on('craft\commerce\elements\Order', Order::EVENT_BEFORE_LINE_ITEMS_REFRESHED, function(OrderLineItemsRefreshEvent $event) use ($lineItem) {
        $event->lineItems = [$lineItem];
        $event->recalculate = true;
    });

    event($event = new LineItemsRefreshing($order, [], false));

    expect($event->lineItems)->toBe([$lineItem])
        ->and($event->recalculate)->toBeTrue();
})->group('yii2-adapter');
