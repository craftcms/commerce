<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Stats\TopCustomers;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

beforeEach(function () {
    $this->fixture = OrdersFixture::seed();
});

test('getData', function (string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, ?Closure $customerData) {
    $stat = new TopCustomers($dateRange, $type, $startDate, $endDate, $this->fixture->storeId);
    $data = $stat->get();

    expect($data)->toBeArray();
    expect($data)->toHaveCount($count);

    if ($count !== 0) {
        $topCustomer = array_shift($data);
        $expected = $customerData($this->fixture);

        foreach (['total', 'average', 'customerId', 'email', 'count'] as $key) {
            expect($topCustomer)->toHaveKey($key);
            expect($topCustomer[$key])->toBe($expected[$key]);
        }

        expect($topCustomer['customer'])->toBeInstanceOf(User::class);
    }
})->with([
    [
        TopCustomers::DATE_RANGE_TODAY,
        'total',
        new DateTime('now')->setTime(0, 0),
        new DateTime('now')->setTime(0, 0),
        1,
        fn (OrdersFixture $fixture) => [
            'total' => 127.94,
            'average' => 63.97,
            'customerId' => $fixture->customer->id,
            'email' => $fixture->customer->email,
            'count' => 2,
        ],
    ],
    [
        TopCustomers::DATE_RANGE_CUSTOM,
        'total',
        new DateTime('7 days ago')->setTime(0, 0),
        new DateTime('5 days ago')->setTime(0, 0),
        0,
        null,
    ],
]);
