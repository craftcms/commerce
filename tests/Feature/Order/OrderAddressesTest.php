<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;

test('hasMatchingAddresses compares billing and shipping addresses attribute-by-attribute', function(array $billingAddress, array $shippingAddress, bool $expected, ?array $attributes = null) {
    $order = new Order();
    $order->setBillingAddress($billingAddress);
    $order->setShippingAddress($shippingAddress);

    expect($order->hasMatchingAddresses($attributes))->toBe($expected);
})->with([
    'all matching' => [
        ['fullName' => 'Johnny Appleseed', 'addressLine1' => '1 Main Street'],
        ['fullName' => 'Johnny Appleseed', 'addressLine1' => '1 Main Street'],
        true,
    ],
    'no matching address' => [
        ['fullName' => 'Johnny Appleseed', 'addressLine1' => '1 Main Street'],
        ['fullName' => 'Johnny Appleseed', 'addressLine1' => '123 Main Street'],
        false,
    ],
    'no matching name' => [
        ['fullName' => 'Johnny Appleseed', 'addressLine1' => '1 Main Street'],
        ['fullName' => 'Jenny Appleseed', 'addressLine1' => '1 Main Street'],
        false,
    ],
    'all matching, full address' => [
        [
            'fullName' => 'Johnny Appleseed',
            'addressLine1' => '1 Main Street',
            'addressLine2' => 'SW',
            'locality' => 'Bend',
            'administrativeArea' => 'OR',
            'countryCode' => 'US',
            'postalCode' => '12345',
        ],
        [
            'fullName' => 'Johnny Appleseed',
            'addressLine1' => '1 Main Street',
            'addressLine2' => 'SW',
            'locality' => 'Bend',
            'administrativeArea' => 'OR',
            'countryCode' => 'US',
            'postalCode' => '12345',
        ],
        true,
    ],
    'matching, restricted to a subset of attributes' => [
        [
            'fullName' => 'Johnny Appleseed',
            'addressLine1' => '1 Main Street',
            'addressLine2' => 'SW',
            'locality' => 'Bend',
            'administrativeArea' => 'OR',
            'countryCode' => 'US',
            'postalCode' => '12345',
        ],
        [
            'fullName' => 'Johnny Appleseed',
            'addressLine1' => '123 Main Street',
            'addressLine2' => 'SW',
            'locality' => 'Bend',
            'administrativeArea' => 'OR',
            'countryCode' => 'US',
            'postalCode' => '12345',
        ],
        true,
        ['addressLine2', 'locality', 'administrativeArea'],
    ],
    'not matching, restricted to a subset of attributes' => [
        [
            'fullName' => 'Johnny Appleseed',
            'addressLine1' => '1 Main Street',
            'addressLine2' => 'SW',
            'locality' => 'Bend',
            'administrativeArea' => 'OR',
            'countryCode' => 'US',
            'postalCode' => '12345',
        ],
        [
            'fullName' => 'Johnny Appleseed',
            'addressLine1' => '123 Main Street',
            'addressLine2' => 'SW',
            'locality' => 'Bend',
            'administrativeArea' => 'OR',
            'countryCode' => 'US',
            'postalCode' => '12345',
        ],
        false,
        ['addressLine1'],
    ],
]);
