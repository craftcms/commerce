<?php
$addresses = [
    'box' => [
        'firstName' => 'Bobx',
        'lastName' => 'Belcherx',
        'addressLine1' => '101 Ocean Avenuex',
        'addressLine2' => '',
        'locality' => 'Long Islandx',
        'postalCode' => '12345',
        'title' => 'TV Show',
        'countryCode' => 'US',
        'administrativeArea' => 'NY',
    ],
    'bttf' => [
        'firstName' => 'Emmett',
        'lastName' => 'Brown',
        'addressLine1' => '1640 Riverside Drive',
        'addressLine2' => '',
        'locality' => 'Hill Valley',
        'postalCode' => '88',
        'title' => 'Movies',
        'countryCode' => 'US',
        'administrativeArea' => 'CA',
    ],
];

return [
    'customer3' => [
        'active' => true,
        'username' => 'customer3',
        'email' => 'customer3@crafttest.com',
        '_shippingAddress' => $addresses['box'],
    ],
];
