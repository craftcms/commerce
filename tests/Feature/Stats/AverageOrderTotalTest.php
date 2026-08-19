<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\AverageOrderTotal;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function () {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function (string $dateRange, DateTime $startDate, DateTime $endDate, ?float $average) {
    $stat = new AverageOrderTotal($dateRange, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    if ($average === null) {
        expect($data)->toBeNull();
    } else {
        expect((float) $data)->toBe($average);
    }
})->with(function () {
    return [
        [
            AverageOrderTotal::DATE_RANGE_TODAY,
            new DateTime('now')->setTime(0, 0),
            new DateTime('now')->setTime(0, 0),
            63.97,
        ],
        [
            AverageOrderTotal::DATE_RANGE_CUSTOM,
            new DateTime('7 days ago')->setTime(0, 0),
            new DateTime('5 days ago')->setTime(0, 0),
            null,
        ],
    ];
});
