<?php

return [
    [
        'id' => '1000',
        'name' => 'My Percentage Sale',
        'description' => 'My test percentage sale.',
        'enabled' => 1,
        'sortOrder' => 1,
        'dateFrom' => null,
        'dateTo' => null,
        'apply' => 'byPercent',
        'applyAmount' => -0.1000,
        'allGroups' => 1,
        'allPurchasables' => 0,
        'allCategories' => 1,
        'ignorePrevious' => null,
        'stopProcessing' => null,
        'categoryRelationshipType' => 'sourceElement',
    ],
    [
        'id' => '1001',
        'name' => 'All Relationships',
        'description' => 'All the relationships.',
        'enabled' => 1,
        'sortOrder' => 2,
        'dateFrom' => null,
        'dateTo' => null,
        'apply' => 'byPercent',
        'applyAmount' => -0.2000,
        'allGroups' => 0,
        'allPurchasables' => 0,
        'allCategories' => 0,
        'ignorePrevious' => null,
        'stopProcessing' => null,
        'categoryRelationshipType' => 'element',
    ]
];