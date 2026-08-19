<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\RepeatCustomers;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function(string $dateRange, DateTime $startDate, DateTime $endDate, int $total, int $repeat, int $percentage) {
    $stat = new RepeatCustomers($dateRange, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data['total'])->toBe($total);
    expect($data['repeat'])->toBe($repeat);
    expect((int) $data['percentage'])->toBe($percentage);
})->with([
    [
        RepeatCustomers::DATE_RANGE_TODAY,
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        1,
        1,
        100,
    ],
    [
        RepeatCustomers::DATE_RANGE_CUSTOM,
        new DateTime('7 days ago')->setTime(0, 0),
        new DateTime('5 days ago')->setTime(0, 0),
        0,
        0,
        0,
    ],
]);
