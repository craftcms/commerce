<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

test('a new order status is sorted after the store’s existing ones', function() {
    $storeId = app(Stores::class)->getPrimaryStore()->id;
    $maxSortOrder = (int)DB::table(Table::ORDERSTATUSES)->where('storeId', $storeId)->max('sortOrder');

    postJson(Url::actionUrl('commerce/order-statuses/save'), [
        'storeId' => $storeId,
        'name' => 'Packed',
        'handle' => 'packed',
        'color' => 'green',
    ])->assertOk();

    expect(DB::table(Table::ORDERSTATUSES)->where('handle', 'packed')->value('sortOrder'))->toBe($maxSortOrder + 1);
});

test('a new store is sorted after the existing ones', function() {
    $maxSortOrder = (int)DB::table(Table::STORES)->max('sortOrder');

    postJson(Url::actionUrl('commerce/stores/save-store'), [
        'name' => 'Outlet',
        'handle' => 'outlet',
        'currency' => 'USD',
        'orderReferenceFormat' => '{{number[:7]}}',
        'freeOrderPaymentStrategy' => Store::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE,
        'minimumTotalPriceStrategy' => Store::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT,
    ])->assertOk();

    expect(DB::table(Table::STORES)->where('handle', 'outlet')->value('sortOrder'))->toBe($maxSortOrder + 1);
});
