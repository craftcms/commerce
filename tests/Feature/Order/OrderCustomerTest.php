<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

function inactiveOrderCustomer(): User
{
    $user = new User();
    $user->username = 'inactive-order-customer';
    $user->email = 'inactive.order.customer@crafttest.com';
    $user->active = false;
    if (!Elements::saveElement($user)) {
        throw new RuntimeException('Could not save inactive user: ' . json_encode($user->errors()->all()));
    }

    return $user;
}

test('setEmail assigns the order to the existing user matching that email, and logs a deprecation warning', function(string $userKey) {
    $user = match ($userKey) {
        'credentialed' => OrdersFixture::seed()->customer,
        'inactive' => inactiveOrderCustomer(),
    };
    $email = $user->email;

    $order = new Order();
    $order->setEmail($email);

    expect($order->getEmail())->toBe($email);
    expect($order->getCustomer())->not->toBeNull();
    expect($order->getCustomer()->email)->toBe($email);

    $setEmailLogs = array_filter(Deprecator::getRequestLogs(), fn($log) => $log->key === Order::class . '::setEmail');
    expect($setEmailLogs)->toHaveCount(1);
})->with([
    'existing credentialed user' => ['credentialed'],
    'existing inactive user' => ['inactive'],
]);

test('setCustomer assigns and clears the order customer', function(string $userKey) {
    $user = match ($userKey) {
        'credentialed' => OrdersFixture::seed()->customer,
        'inactive' => inactiveOrderCustomer(),
    };

    $order = new Order();
    $order->setCustomer($user);

    expect($order->getEmail())->toBe($user->email);
    expect($order->getCustomer())->not->toBeNull();
    expect($order->getCustomer()->email)->toBe($user->email);
    expect($order->getCustomer()->id)->toBe($user->id);
    expect($order->getCustomerId())->toBe($user->id);

    $order->setCustomer();

    expect($order->getCustomer())->toBeNull();
    expect($order->getCustomerId())->toBeNull();
    expect($order->getEmail())->toBeNull();
})->with([
    'existing credentialed user' => ['credentialed'],
    'existing inactive user' => ['inactive'],
]);
