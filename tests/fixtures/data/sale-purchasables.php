<?php

use craft\commerce\elements\Variant;

return [
    [
        'id' => '9000',
        'saleId' => '1000',
        'purchasableId' => Variant::find()->sku('rad-hood')->one()->id,
        'purchasableType' => Variant::class,
    ],
    [
        'id' => '9001',
        'saleId' => '1001',
        'purchasableId' => Variant::find()->sku('hct-white')->one()->id,
        'purchasableType' => Variant::class,
    ],
];