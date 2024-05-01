<?php

return [
    'primary' => [
        '_load' => false,
        '_sites' => [1000],
        'settings' => [
            '_storeLocationAddress' => [
                'countryCode' => 'US',
                'administrativeArea' => 'NY',
                'locality' => 'New York',
                'addressLine1' => '123 Fake Street',
                'postalCode' => '10001',
            ],
            'countries' => [
                'US',
                'CA',
            ],
        ],
    ],
    'ukStore' => [
        '_sites' => [1002],
        'name' => 'UK Store',
        'handle' => 'ukStore',
        'primary' => false,
        'settings' => [
            '_storeLocationAddress' => [
                'countryCode' => 'GB',
                'administrativeArea' => 'ENG',
                'locality' => 'London',
                'addressLine1' => '123 Fake Street',
                'postalCode' => 'W1A 1AA',
            ],
            'countries' => [
                'GB',
                'FR',
            ],
        ],
    ],
    'euStore' => [
        '_sites' => [1001],
        'name' => 'EU Store',
        'handle' => 'euStore',
        'primary' => false,
        'settings' => [
            '_storeLocationAddress' => [
                'countryCode' => 'NL',
                'locality' => 'Amsterdam',
                'addressLine1' => '123 Dam Street',
                'postalCode' => 'AM1 1DM',
            ],
            'countries' => [
                'NL',
            ],
        ],
    ],
];
