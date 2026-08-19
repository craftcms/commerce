<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Stats\TopProducts;
use CraftCms\Commerce\Stats\TopProductTypes;
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

test('getData', function (string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, ?array $productTypeData) {
    $stat = new TopProductTypes($dateRange, $type, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data)->toHaveCount($count);

    if ($count !== 0) {
        $topProductType = array_shift($data);

        expect($topProductType['id'])->toBe($this->fixture->product->typeId);

        foreach (['name', 'qty', 'revenue'] as $key) {
            expect($topProductType)->toHaveKey($key);
            expect($topProductType[$key])->toBe($productTypeData[$key]);
        }

        expect($topProductType['productType'])->toBeInstanceOf(ProductType::class);
    }
})->with(function () {
    return [
        [
            TopProducts::DATE_RANGE_TODAY,
            'revenue',
            new DateTime('now')->setTime(0, 0),
            new DateTime('now')->setTime(0, 0),
            1,
            [
                'name' => 'T-Shirts',
                'qty' => 6,
                'revenue' => 127.94,
            ],
        ],
        [
            TopProducts::DATE_RANGE_CUSTOM,
            'revenue',
            new DateTime('7 days ago')->setTime(0, 0),
            new DateTime('5 days ago')->setTime(0, 0),
            0,
            null,
        ],
    ];
});
