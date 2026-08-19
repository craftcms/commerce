<?php

declare(strict_types=1);

use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Stats\TopProducts;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function () {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function (string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, ?Closure $productData) {
    $stat = new TopProducts($dateRange, $type, $startDate, $endDate, storeId: $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data)->toHaveCount($count);

    if ($count !== 0) {
        $topProduct = array_shift($data);
        $expected = $productData($this->fixture);

        foreach (['id', 'title', 'qty', 'revenue'] as $key) {
            expect($topProduct)->toHaveKey($key);
            expect($topProduct[$key])->toBe($expected[$key]);
        }

        expect($topProduct['product'])->toBeInstanceOf(Product::class);
    }
})->with([
    [
        TopProducts::DATE_RANGE_TODAY,
        'revenue',
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        1,
        fn (OrdersFixture $fixture) => [
            'id' => $fixture->product->id,
            'title' => 'Hypercolor T-Shirt',
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
]);
