<?php

use craft\commerce\elements\Variant;

return [
    [
        'groupId' => $this->groupIds['categories'],
        'title' => 'Commerce Category',

        'fieldLayoutType' => 'craft\elements\Category',
        'commerceProductVariants' => Variant::find()->sku(['hct-white'])->ids(),
    ],
    [
        'groupId' => $this->groupIds['categories'],
        'title' => 'Commerce Category #2',

        'fieldLayoutType' => 'craft\elements\Category',
        'commerceProductVariants' => Variant::find()->sku(['hct-white'])->ids(),
    ],
];
