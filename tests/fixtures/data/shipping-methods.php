<?php

return [
    'us-only' => [
        'name' => 'US Shipping',
        'handle' => 'usShipping',
        'storeId' => 1, // Primary
        'enabled' => true,
    ],
    'eu-disabled' => [
        'name' => 'EU Shipping',
        'handle' => 'euShipping',
        'storeId' => 1, // Primary
        'enabled' => false,
    ],
];
