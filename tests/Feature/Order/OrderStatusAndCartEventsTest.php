<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\InactiveCartsPurging;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
