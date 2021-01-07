<?php

return [
    [
        // This order status starts in the DB
        'id' => '1',
        'name' => 'New',
        'handle' => 'new',
        'color' => 'green',
        'sortOrder' => 1,
        'default' => 1,
    ],
    [
        'name' => 'Processing',
        'handle' => 'processing',
        'color' => 'yellow',
        'sortOrder' => 2,
        'default' => 0,
    ],
    [
        'name' => 'Shipped',
        'handle' => 'shipped',
        'color' => 'purple',
        'sortOrder' => 3,
        'default' => 0,
    ],
    [
        'name' => 'Delivered',
        'handle' => 'delivered',
        'color' => 'black',
        'sortOrder' => 4,
        'default' => 0,
    ],
];