<?php

use craft\commerce\fields\Variants;

return [
    [
        'uid' => 'field-layout-1000----------------uid',
        'type' => 'commerce_categories_fieldlayout',
        'tabs' => [
            [
                'name' => 'Tab 1', // Required
                'fields' => [
                    [
                        'layout-link' => [
                            'required' => true
                        ],
                        'field' => [
                            'uid' => 'field-1001-----------------------uid',
                            'name' => 'Commerce Product Variants',
                            'handle' => 'commerceProductVariants',
                            'fieldType' => Variants::class,
                        ]
                    ],
                ]
            ]
        ]
    ]
];