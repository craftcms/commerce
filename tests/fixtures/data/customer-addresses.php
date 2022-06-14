<?php
$addresses = [
    'box' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'addressLine1' => '23 Woodworth ',
        'addressLine2' => 'Seaside',
        'locality' => 'County Island',
        'postalCode' => '12345',
        'title' => 'TV Show',
        'countryCode' => 'US',
        'administrativeArea' => 'NY',
    ]
];

return [
    'customer3' => [
        'active' => true,
        'username' => 'customer3',
        'email' => 'customer3@crafttest.com',
        '_shippingAddress' => $addresses['box'],
    ],
];
