<?php

use craft\commerce\elements\Variant;

return [
    [
        'groupId' => $this->groupIds['categories'],
        'title' => 'Commerce Category',

        'fieldLayoutType' => 'commerce_categories_fieldlayout',
        'field:commerceProductVariants' => Variant::find()->sku(['hct-white'])->ids(),
    ],
    [
        'groupId' => $this->groupIds['categories'],
        'title' => 'Commerce Category #2',

        'fieldLayoutType' => 'commerce_categories_fieldlayout',
        'field:commerceProductVariants' => [],
    ],
];