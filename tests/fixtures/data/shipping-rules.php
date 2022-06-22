<?php

return [
    'us-only' => [
        'name' => 'US Shipping',
        'enabled' => true,
        'methodId' => 'usShipping',
        'priority' => 0,
    ],
    'us-only-2' => [
        'name' => 'US Shipping 2',
        'enabled' => true,
        'methodId' => 'usShipping',
        'priority' => 1,
    ],
    'eu-disabled' => [
        'name' => 'EU Shipping',
        'enabled' => false,
        'methodId' => 'euShipping',
    ],
];