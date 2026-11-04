<?php

declare(strict_types=1);

use craft\commerce\events\CartPurgeEvent;
use craft\commerce\events\DefaultLineItemStatusEvent;
use craft\commerce\events\DefaultOrderStatusEvent;
use craft\commerce\events\OrderStatusEmailsEvent;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\services\Carts as LegacyCarts;
use craft\commerce\services\LineItemStatuses as LegacyLineItemStatuses;
use craft\commerce\services\OrderHistories as LegacyOrderHistories;
use craft\commerce\services\OrderStatuses as LegacyOrderStatuses;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\DefaultLineItemStatusResolving;
use CraftCms\Commerce\Order\Events\DefaultOrderStatusResolving;
use CraftCms\Commerce\Order\Events\InactiveCartsPurging;
use CraftCms\Commerce\Order\Events\OrderStatusChanged;
use CraftCms\Commerce\Order\Events\OrderStatusEmailsResolving;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use yii\base\Event as YiiEvent;

// These test the legacy craft\commerce event bridge, which needs the yii2 adapter.
pest()->group('yii2-adapter');

afterEach(function() {
    YiiEvent::offAll();
});

test('legacy listeners can change the default line item status', function() {
    $status = new LineItemStatus();

    YiiEvent::on(LegacyLineItemStatuses::class, LegacyLineItemStatuses::EVENT_DEFAULT_LINE_ITEM_STATUS, function(DefaultLineItemStatusEvent $event) use ($status) {
        $event->lineItemStatus = $status;
    });

    event($event = new DefaultLineItemStatusResolving(new LineItem()));

    expect($event->lineItemStatus)->toBe($status);
});

test('legacy listeners can change the default order status', function() {
    $status = new OrderStatus();
    $order = new Order();
    $received = null;

    YiiEvent::on(LegacyOrderStatuses::class, LegacyOrderStatuses::EVENT_DEFAULT_ORDER_STATUS, function(DefaultOrderStatusEvent $event) use ($status, &$received) {
        $received = $event->order;
        $event->orderStatus = $status;
    });

    event($event = new DefaultOrderStatusResolving(null, $order));

    expect($event->orderStatus)->toBe($status)
        ->and($received)->toBe($order);
});

test('legacy listeners can change or cancel order status emails', function(bool $cancel) {
    YiiEvent::on(LegacyOrderStatuses::class, LegacyOrderStatuses::EVENT_ORDER_STATUS_CHANGE_EMAILS, function(OrderStatusEmailsEvent $event) use ($cancel) {
        $event->emails = [];
        $event->isValid = !$cancel;
    });

    event($event = new OrderStatusEmailsResolving(new OrderHistory(), new Order(), ['email']));

    expect($event->emails)->toBe([])
        ->and($event->isValid)->toBe(!$cancel);
})->with([
    'change' => [false],
    'cancel' => [true],
]);

test('legacy listeners receive order status changes', function() {
    $history = new OrderHistory();
    $order = new Order();
    $received = null;

    YiiEvent::on(LegacyOrderHistories::class, LegacyOrderHistories::EVENT_ORDER_STATUS_CHANGE, function(OrderStatusEvent $event) use (&$received) {
        $received = [$event->orderHistory, $event->order];
    });

    event(new OrderStatusChanged($history, $order));

    expect($received)->toBe([$history, $order]);
});

test('legacy listeners can narrow or cancel the inactive cart purge', function() {
    $kept = new Order();
    Elements::saveElement($kept, false);
    $purged = new Order();
    Elements::saveElement($purged, false);

    $query = fn() => DB::table(Table::ORDERS, 'orders')
        ->select('orders.id')
        ->whereIn('orders.id', [$kept->id, $purged->id]);

    YiiEvent::on(LegacyCarts::class, LegacyCarts::EVENT_BEFORE_PURGE_INACTIVE_CARTS, function(CartPurgeEvent $event) use ($kept) {
        $event->inactiveCartsQuery->andWhere(['not', ['orders.id' => $kept->id]]);
    });

    event($event = new InactiveCartsPurging($query()));

    expect($event->isValid)->toBeTrue()
        ->and($event->inactiveCartsQuery->pluck('id')->all())->toBe([$purged->id]);

    YiiEvent::offAll();
    YiiEvent::on(LegacyCarts::class, LegacyCarts::EVENT_BEFORE_PURGE_INACTIVE_CARTS, function(CartPurgeEvent $event) {
        $event->isValid = false;
    });

    event($event = new InactiveCartsPurging($query()));

    expect($event->isValid)->toBeFalse();
});

test('purging inactive carts only deletes old incomplete orders, unless a listener cancels it', function(bool $cancel) {
    $orders = [];
    foreach (['old cart' => [100, false], 'recent cart' => [1, false], 'old completed order' => [100, true]] as $key => [$daysAgo, $isCompleted]) {
        $order = new Order();
        Elements::saveElement($order, false);
        DB::table(Table::ORDERS)->where('id', $order->id)->update([
            'isCompleted' => $isCompleted,
            'dateUpdated' => now()->subDays($daysAgo)->toDateTimeString(),
        ]);
        $orders[$key] = $order->id;
    }

    if ($cancel) {
        Event::listen(InactiveCartsPurging::class, fn(InactiveCartsPurging $event) => $event->isValid = false);
    }

    $purged = app(Carts::class)->purgeIncompleteCarts();
    $remaining = DB::table(CraftTable::ELEMENTS)->whereIn('id', $orders)->pluck('id')->all();

    expect($purged)->toBe($cancel ? 0 : 1)
        ->and($remaining)->toEqualCanonicalizing($cancel ? array_values($orders) : [$orders['recent cart'], $orders['old completed order']]);
})->with([
    'purged' => [false],
    'cancelled' => [true],
]);
