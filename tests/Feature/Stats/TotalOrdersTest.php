<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\TotalOrders;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function(string $case) {
    // Computed here, after the app has booted and pinned its timezone (see TestCase::setUp()),
    // rather than in the ->with() dataset — datasets are resolved before beforeEach()/app boot,
    // so a `new DateTime('now')` captured there can land on a different calendar date than one
    // computed here (and than the one TotalOrders computes internally), depending on how far the
    // pre-boot default PHP timezone is from the app's pinned one.
    $now = new DateTime();

    [$dateRange, $startDate, $endDate, $total, $daysDiff] = match ($case) {
        'today' => [
            TotalOrders::DATE_RANGE_TODAY,
            (clone $now)->setTime(0, 0),
            (clone $now)->setTime(0, 0),
            2,
            1,
        ],
        'custom' => [
            TotalOrders::DATE_RANGE_CUSTOM,
            (clone $now)->modify('-7 days')->setTime(0, 0),
            (clone $now)->modify('-5 days')->setTime(0, 0),
            0,
            3,
        ],
    };

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
})->with(['today', 'custom']);
