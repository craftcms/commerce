<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\NewCustomers;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function(string $dateRange, DateTime $startDate, DateTime $endDate, ?float $count) {
    $stat = new NewCustomers($dateRange, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeNumeric();
    expect((float) $data)->toBe($count);
})->with([
    [
        NewCustomers::DATE_RANGE_CUSTOM,
        new DateTime('2 days ago')->setTime(0, 0),
        new DateTime('0 days ago')->setTime(0, 0),
        1.0,
    ],
    [
        NewCustomers::DATE_RANGE_TODAY,
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        0.0,
    ],
    [
        NewCustomers::DATE_RANGE_CUSTOM,
        new DateTime('7 days ago')->setTime(0, 0),
        new DateTime('5 days ago')->setTime(0, 0),
        0.0,
    ],
]);
