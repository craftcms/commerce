<?php

use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\elements\conditions\addresses\CountryConditionRule;

return [
    'us-zone' => [
        'name' => 'US Zone',
        'description' => 'US Zone',
        'storeId' => 1, // Primary
        'condition' => [
            'elementType' => null,
            'fieldContext' => 'global',
            'class' => ZoneAddressCondition::class,
            'conditionRules' => [
                [
                    'class' => CountryConditionRule::class,
                    'uid' => '03bfbb2a-341d-4d2b-905c-c56ec130546f',
                    'operator' => 'in',
                    'values' => ['US'],
                ],
            ],
        ],
    ],
    'eu-zone' => [
        'name' => 'EU Zone',
        'description' => 'EU Zone',
        'storeId' => 1, // Primary
        'condition' => [
            'elementType' => null,
            'fieldContext' => 'global',
            'class' => ZoneAddressCondition::class,
            'conditionRules' => [
                [
                    'class' => CountryConditionRule::class,
                    'uid' => '03bfbb2a-341d-4d2b-905c-c56ec130546Z',
                    'operator' => 'in',
                    'values' => ['GB', 'FR'],
                ],
            ],
        ],
    ],
];
