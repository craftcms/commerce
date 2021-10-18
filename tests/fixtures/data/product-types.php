<?php

return [
    [
        'id' => '2000',
        'name' => 'Hoodies',
        'handle' => 'hoodies',
        'hasDimensions' => false,
        'hasVariants' => false,
        'descriptionFormat' => '{product.title}',
        'variantTitleFormat' => '{product.title}',
        'productTitleFormat' => '',
        'hasProductTitleField' => true,
        'hasVariantTitleField' => false,
    ],
    [
        'id' => '2001',
        'name' => 'T-Shirts',
        'handle' => 'tShirts',
        'hasDimensions' => false,
        'hasVariants' => true,
        'descriptionFormat' => '{product.title} - {title}',
        'variantTitleFormat' => '{product.title}',
        'productTitleFormat' => '',
        'hasProductTitleField' => true,
        'hasVariantTitleField' => false,
    ]
];