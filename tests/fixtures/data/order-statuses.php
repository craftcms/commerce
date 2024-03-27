<?php

return [
    [
        // This order status starts in the DB
        'id' => '1',
        'storeId' => 1, // Primary
        'name' => 'New',
        'handle' => 'new',
        'color' => 'green',
        'sortOrder' => 1,
        'default' => 1,
        // Because this is already in the DB, retrieve the `uid`
        'uid' => \craft\commerce\records\OrderStatus::find()->where(['id' => '1'])->one()->uid,
    ],
    [
        'storeId' => 1, // Primary
        'name' => 'Processing',
        'handle' => 'processing',
        'color' => 'yellow',
        'sortOrder' => 2,
        'default' => 0,
    ],
    [
        'storeId' => 1, // Primary
        'name' => 'Shipped',
        'handle' => 'shipped',
        'color' => 'purple',
        'sortOrder' => 3,
        'default' => 0,
    ],
    [
        'storeId' => 1, // Primary
        'name' => 'Delivered',
        'handle' => 'delivered',
        'color' => 'black',
        'sortOrder' => 4,
        'default' => 0,
    ],
];
