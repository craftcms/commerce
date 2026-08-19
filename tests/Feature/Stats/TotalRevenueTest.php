<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\TotalRevenue;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function () {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function (string $dateRange, DateTime $startDate, DateTime $endDate, int $count, float $revenue, string $type) {
    $stat = new TotalRevenue($dateRange, $startDate, $endDate, $this->fixture->storeId);
    $stat->type = $type;
    $data = $stat->get();

    expect($data)->toBeArray();

    $todaysStats = array_pop($data);
    expect($todaysStats)->toHaveKey('count');
    expect($todaysStats)->toHaveKey('revenue');
    expect($todaysStats)->toHaveKey('datekey');
    expect($todaysStats['count'])->toBe($count);
    expect((float) $todaysStats['revenue'])->toBe($revenue);
})->with([
    [
        TotalRevenue::DATE_RANGE_TODAY,
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        2,
        127.94,
        TotalRevenue::TYPE_TOTAL,
    ],
    [
        TotalRevenue::DATE_RANGE_TODAY,
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        2,
        0.0,
        TotalRevenue::TYPE_TOTAL_PAID,
    ],
]);
