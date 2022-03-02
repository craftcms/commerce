<?php

use craft\commerce\fields\Variants;
use craft\fields\PlainText;

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
                            // TODO figure out why not having this set breaks tests using this fixture
                            'context' => 'foo'
                        ]
                    ],
                ]
            ]
        ]
    ],
    [
        // Because User elements fetch layout by type
        'type' => 'craft\elements\User',
        'tabs' => [
            [
                'name' => 'Tab 1',
                'fields' => [
                    [
                        'name' => 'My Test Text',
                        'handle' => 'myTestText',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                ]
            ]
        ]
    ],
];