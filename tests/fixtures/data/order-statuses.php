<?php

return [
    [
        // This order status starts in the DB
        'id' => '1',
        'name' => 'New',
        'handle' => 'new',
        'color' => 'green',
        'sortOrder' => 1,
        'default' => true,
    ],
    [
        'name' => 'Processing',
        'handle' => 'processing',
        'color' => 'yellow',
        'sortOrder' => 2,
        'default' => false,
    ],
    [
        'name' => 'Shipped',
        'handle' => 'shipped',
        'color' => 'purple',
        'sortOrder' => 3,
        'default' => false,
    ],
    [
        'name' => 'Delivered',
        'handle' => 'delivered',
        'color' => 'black',
        'sortOrder' => 4,
        'default' => false,
    ],
];