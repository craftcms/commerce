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
    [
        'id' => 1,
        'productTypeId' => 2000,
        'shippingCategoryId' => $shippingCategoryId,
    ],
];
