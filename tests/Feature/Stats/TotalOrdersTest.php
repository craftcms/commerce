<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\TotalOrders;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function () {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function (string $dateRange, DateTime $startDate, DateTime $endDate, int $total, int $daysDiff) {
    $stat = new TotalOrders($dateRange, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data)->toHaveKey('total');
    expect($data['total'])->toBe($total);
    expect($data)->toHaveKey('chart');
    expect($data['chart'])->toBeArray();
    expect($data['chart'])->toHaveKey($startDate->format('Y-m-d'));
    expect($data['chart'])->toHaveKey($endDate->format('Y-m-d'));
    expect($data['chart'])->toHaveCount($daysDiff);

    $firstItem = array_shift($data['chart']);
    expect($firstItem)->toHaveKey('total');
    expect($firstItem)->toHaveKey('datekey');
    expect($firstItem['datekey'])->toBe($startDate->format('Y-m-d'));
    expect($firstItem['total'])->toBe($total);
})->with([
    'today' => [
        TotalOrders::DATE_RANGE_TODAY,
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        2,
        1,
    ],
    'custom' => [
        TotalOrders::DATE_RANGE_CUSTOM,
        new DateTime('7 days ago')->setTime(0, 0),
        new DateTime('5 days ago')->setTime(0, 0),
        0,
        3,
    ],
]);
