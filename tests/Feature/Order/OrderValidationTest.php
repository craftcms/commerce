<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;

test('validate reports incomplete address attributes, and passes once they are filled in', function() {
    $order = new Order();
    $order->setBillingAddress([]);
    $order->setShippingAddress(['addressLine1' => '1 Main Street']);

    expect($order->validate())->toBeFalse();
    expect($order->getErrors())->not->toBeEmpty();
    expect($order->getErrors())->toHaveKey('billingAddress.administrativeArea');
    expect($order->getErrors())->toHaveKey('billingAddress.locality');
    expect($order->getErrors())->toHaveKey('billingAddress.postalCode');
    expect($order->getErrors())->toHaveKey('billingAddress.addressLine1');
    expect($order->getErrors())->toHaveKey('shippingAddress.administrativeArea');
    expect($order->getErrors())->toHaveKey('shippingAddress.locality');
    expect($order->getErrors())->toHaveKey('shippingAddress.postalCode');

    $order->setBillingAddress([
        'addressLine1' => 'Downtown',
        'locality' => 'LA',
        'administrativeArea' => 'CA',
        'postalCode' => '90210',
    ]);
    $order->setShippingAddress([
        'addressLine1' => '1 Main Street',
        'locality' => 'LA',
        'administrativeArea' => 'CA',
        'postalCode' => '90210',
    ]);

    expect($order->validate())->toBeTrue();
    expect($order->getErrors())->toBeEmpty();
});
