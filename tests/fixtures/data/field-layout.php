<?php

use craft\commerce\fields\Variants;
use craft\fields\PlainText;

return [
    [
        'type' => 'craft\elements\Category',
        'tabs' => [
            [
                'name' => 'Tab 1', // Required
                'fields' => [
                    [
                        'layout-link' => [
                            'required' => true,
                        ],
                        'field' => [
                            'uid' => 'field-1001-----------------------uid',
                            'name' => 'Commerce Product Variants',
                            'handle' => 'commerceProductVariants',
                            'fieldType' => Variants::class,
                            // @TODO Investigate why omitting `context` breaks tests that consume this field-layout fixture
                            'context' => 'foo',
                        ],
                    ],
                ],
            ],
        ],
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
                ],
            ],
        ],
    ],
    [
        'type' => 'craft\elements\Address',
        'tabs' => [
            [
                'name' => 'Tab 1',
                'fields' => [
                    [
                        'name' => 'Test Phone',
                        'handle' => 'testPhone',
                        'type' => \craft\fields\Number::class,
                        'required' => false,
                    ],
                ],
            ],
        ],
    ],
    [
        'uid' => 'product-layout-1001--------------uid',
        'type' => 'craft\elements\Product',
        'tabs' => [
            [
                'name' => 'Tab 1',
                'fields' => [
                    [
                        'name' => 'My Heading Field',
                        'handle' => 'myHeadingField',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                ],
            ],
        ],
    ],
    [
        'uid' => 'variant-layout-1001--------------uid',
        'type' => 'craft\elements\Variant',
        'tabs' => [
            [
                'name' => 'Tab 1',
                'fields' => [
                    [
                        'name' => 'My VariantHeading Field',
                        'handle' => 'myVariantHeadingField',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                ],
            ],
        ],
    ],
];
