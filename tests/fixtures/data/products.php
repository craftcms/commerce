<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

$shippingCategoryId = (new \craft\db\Query())
    ->select('id')
    ->from(\craft\commerce\db\Table::SHIPPINGCATEGORIES)
    ->where(['handle' => 'anotherShippingCategory'])
    ->scalar();

return [
    'rad-hoodie' => [
        'typeId' => '2000',
        'title' => 'Rad Hoodie',
        'slug' => 'rad-hoodie',
        'enabled' => 1,
        'enabledForSite' => 1,
        'postDate' => (new DateTime('now')),
        '_variants' => [
            'new_0' => [
                'availableForPurchase' => 1,
                'promotable' => 1,
                'shippingCategoryId' => $shippingCategoryId,
                'taxCategoryId' => 101,
                'title' => 'Rad Hoodie',
                'slug' => 'rad-hoodie',
                'isDefault' => 1,
                'sku' => 'rad-hood',
                'basePrice' => 123.99,
                'sortOrder' => 0,
                'width' => null,
                'height' => null,
                'length' => null,
                'weight' => null,
                'inventoryTracked' => 0,
                'minQty' => null,
                'maxQty' => null,
            ],
        ],
    ],
    'hypercolor-tshirt' => [
        'typeId' => '2001',
        'title' => 'Hypercolor T-Shirt',
        'slug' => 'hypercolor-tshirt',
        'enabled' => 1,
        'enabledForSite' => 1,
        'postDate' => (new DateTime('now')),
        '_variants' => [
            'new1' => [
                'availableForPurchase' => 1,
                'promotable' => 1,
                'shippingCategoryId' => $shippingCategoryId,
                'taxCategoryId' => 101,
                'title' => 'White',
                'slug' => 'white',
                'isDefault' => 1,
                'sku' => 'hct-white',
                'basePrice' => 19.99,
                'sortOrder' => 0,
                'width' => null,
                'height' => null,
                'length' => null,
                'weight' => null,
                'inventoryTracked' => 0,
                'minQty' => null,
                'maxQty' => null,
            ],
            'new_2' => [
                'availableForPurchase' => 1,
                'promotable' => 1,
                'shippingCategoryId' => $shippingCategoryId,
                'taxCategoryId' => 101,
                'title' => 'Blue',
                'slug' => 'blue',
                'isDefault' => 0,
                'sku' => 'hct-blue',
                'basePrice' => 21.99,
                'sortOrder' => 1,
                'width' => null,
                'height' => null,
                'length' => null,
                'weight' => null,
                'inventoryTracked' => 0,
                'minQty' => null,
                'maxQty' => null,
            ],
        ],
    ],
];
