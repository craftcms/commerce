<?php

return [
    'primary' => [
        '_load' => false,
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
                'CA'
            ],
        ]
    ],
    'ukStore' => [
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
                'FR'
            ],
        ]
    ]
];