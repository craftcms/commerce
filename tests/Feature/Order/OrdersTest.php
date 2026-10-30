<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('getOrderById returns the matching order', function() {
    $fixture = OrdersFixture::seed();
    $expected = $fixture->orders['completed-new'];

    $order = app(Orders::class)->getOrderById($expected->id);

    expect($order)->toBeInstanceOf(Order::class);
    expect($order->id)->toBe($expected->id);
});

test('getOrderByNumber returns the matching order, and null for an unknown number', function() {
    $fixture = OrdersFixture::seed();
    $expected = $fixture->orders['completed-new'];

    $order = app(Orders::class)->getOrderByNumber($expected->number);

    expect($order)->toBeInstanceOf(Order::class);
    expect($order->number)->toBe($expected->number);
    expect($order->id)->toBe($expected->id);

    expect(app(Orders::class)->getOrderByNumber('invalid'))->toBeNull();
});

test('getOrdersByCustomer returns all completed orders for a customer id', function() {
    $fixture = OrdersFixture::seed();

    $orders = app(Orders::class)->getOrdersByCustomer($fixture->customer->id);

    expect($orders)->toBeArray();
    expect($orders)->toHaveCount(3);
    foreach ($orders as $order) {
        expect($order->id)->toBeIn([
            $fixture->orders['completed-new']->id,
            $fixture->orders['completed-new-past']->id,
            $fixture->orders['completed-shipped']->id,
        ]);
    }
});

test('getOrdersByCustomer returns all completed orders for a customer element', function() {
    $fixture = OrdersFixture::seed();

    $orders = app(Orders::class)->getOrdersByCustomer($fixture->customer);

    expect($orders)->toBeArray();
    expect($orders)->toHaveCount(3);
});

test('getOrdersByEmail returns all completed orders for an email address', function() {
    $fixture = OrdersFixture::seed();
    $email = $fixture->orders['completed-new']->getEmail();

    $orders = app(Orders::class)->getOrdersByEmail($email);

    expect($orders)->toBeArray();
    expect($orders)->toHaveCount(3);
    foreach ($orders as $order) {
        expect($order->getEmail())->toBe($email);
    }
});
