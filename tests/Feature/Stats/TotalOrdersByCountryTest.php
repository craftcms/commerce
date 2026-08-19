<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\TotalOrdersByCountry;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function(string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, array $countryData) {
    $stat = new TotalOrdersByCountry($dateRange, $type, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data)->toHaveCount($count);

    if ($count !== 0) {
        $firstItem = array_shift($data);

        foreach ($countryData as $key => $value) {
            expect($firstItem)->toHaveKey($key);
            expect($firstItem[$key])->toBe($value);
        }
    }
})->with([
    [
        TotalOrdersByCountry::DATE_RANGE_TODAY,
        'shipping',
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        1,
        [
            'total' => 2,
            'name' => 'United States',
            'countryCode' => 'US',
        ],
    ],
    [
        TotalOrdersByCountry::DATE_RANGE_CUSTOM,
        'shipping',
        new DateTime('7 days ago')->setTime(0, 0),
        new DateTime('5 days ago')->setTime(0, 0),
        0,
        [],
    ],
]);
