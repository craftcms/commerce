<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

return [
    [
        'typeId' => '2000',
        'title' => 'Rad Hoodie',
        'slug' => 'rad-hoodie',
        'enabled' => 1,
        'enabledForSite' => 1,
        'availableForPurchase' => 1,
        'promotable' => 1,
        'postDate' => (new DateTime('now')),
        '_variants' => [
            'new_0' => [
                'title' => 'Rad Hoodie',
                'slug' => 'rad-hoodie',
                'isDefault' => 1,
                'sku' => 'rad-hood',
                'price' => 123.99,
                'sortOrder' => 0,
                'width' => null,
                'height' => null,
                'length' => null,
                'weight' => null,
                'stock' => null,
                'hasUnlimitedStock' => 1,
                'minQty' => null,
                'maxQty' => null,
            ],
        ]
    ],
    [
        'typeId' => '2001',
        'title' => 'Hypercolor T-Shirt',
        'slug' => 'hypercolor-tshirt',
        'enabled' => 1,
        'enabledForSite' => 1,
        'availableForPurchase' => 1,
        'promotable' => 1,
        'postDate' => (new DateTime('now')),
        '_variants' => [
            'new1' => [
                'title' => 'White',
                'slug' => 'white',
                'isDefault' => 1,
                'sku' => 'hct-white',
                'price' => 19.99,
                'sortOrder' => 0,
                'width' => null,
                'height' => null,
                'length' => null,
                'weight' => null,
                'stock' => null,
                'hasUnlimitedStock' => 1,
                'minQty' => null,
                'maxQty' => null,
            ],
            'new_2' => [
                'title' => 'Blue',
                'slug' => 'blue',
                'isDefault' => 0,
                'sku' => 'hct-blue',
                'price' => 21.99,
                'sortOrder' => 1,
                'width' => null,
                'height' => null,
                'length' => null,
                'weight' => null,
                'stock' => null,
                'hasUnlimitedStock' => 1,
                'minQty' => null,
                'maxQty' => null,
            ]
        ]
    ]
];
