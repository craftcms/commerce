<?php

declare(strict_types=1);

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('the default order status created on install can be renamed', function() {
    $storeId = app(Stores::class)->getPrimaryStore()->id;
    $orderStatus = app(OrderStatuses::class)->getDefaultOrderStatus($storeId);

    expect(Str::isUuid($orderStatus->uid))->toBeTrue();

    $orderStatus->name = 'Received';

    expect(app(OrderStatuses::class)->saveOrderStatus($orderStatus, $orderStatus->getEmailIds()))->toBeTrue()
        ->and(DB::table(Table::ORDERSTATUSES)->where('id', $orderStatus->id)->value('name'))->toBe('Received');
});
