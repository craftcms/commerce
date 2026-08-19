<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Stats\TopPurchasables;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function () {
    $this->fixture = OrdersFixture::seed();

    $admin = User::find()->admin(true)->one();
    $this->actingAs($admin, 'craft');
    // `actingAs()` doesn't retroactively update the already-bound `request()` singleton's user
    // resolver in this Testbench setup, and `getViewableProductTypeIds()` reads the current user
    // via `request()->craftUser()` rather than the `Auth` facade.
    request()->setUserResolver(fn () => $admin);
});

test('getData', function (string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, ?Closure $purchasableData) {
    $stat = new TopPurchasables($dateRange, $type, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data)->toHaveCount($count);

    if ($count !== 0) {
        $topPurchasable = array_shift($data);
        $expected = $purchasableData($this->fixture);

        foreach (['purchasableId', 'description', 'sku', 'qty', 'revenue'] as $key) {
            expect($topPurchasable)->toHaveKey($key);
            expect($topPurchasable[$key])->toBe($expected[$key]);
        }
    }
})->with([
    'date-today' => [
        TopPurchasables::DATE_RANGE_TODAY,
        'revenue',
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        2,
        fn (OrdersFixture $fixture) => [
            'purchasableId' => $fixture->blue->id,
            'description' => $fixture->blue->getDescription(),
            'sku' => 'hct-blue',
            'qty' => 4,
            'revenue' => 87.96,
        ],
    ],
    'date-custom' => [
        TopPurchasables::DATE_RANGE_CUSTOM,
        'qty',
        new DateTime('7 days ago')->setTime(0, 0),
        new DateTime('5 days ago')->setTime(0, 0),
        0,
        null,
    ],
]);
