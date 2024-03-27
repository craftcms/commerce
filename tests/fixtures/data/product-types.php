<?php

return [
    'hoodies' => [
        'id' => '2000',
        'name' => 'Hoodies',
        'handle' => 'hoodies',
        'hasDimensions' => false,
        'maxVariants' => 1,
        'descriptionFormat' => '{product.title}',
        'variantTitleFormat' => '{product.title}',
        'productTitleFormat' => '',
        'hasProductTitleField' => true,
        'hasVariantTitleField' => false,
    ],
    'tees' => [
        'id' => '2001',
        'name' => 'T-Shirts',
        'handle' => 'tShirts',
        'hasDimensions' => false,
        'maxVariants' => null,
        'descriptionFormat' => '{product.title} - {title}',
        'variantTitleFormat' => '{product.title}',
        'productTitleFormat' => '',
        'hasProductTitleField' => true,
        'hasVariantTitleField' => false,
    ],
];
