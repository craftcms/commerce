<?php

use craft\commerce\elements\Variant;
use craft\elements\Category;

$hctWhite = Variant::find()->sku('hct-white')->one();
$allPurchasables = $hctWhite ? [$hctWhite->id] : [];

$radHood = Variant::find()->sku('rad-hood')->one();
$percentagePurchasables = $radHood ? [$radHood->id] : [];

$categoryIds = Category::find()->title(['Commerce Category','Commerce Category #2'])->ids();

return [
    'percentageSale' => [
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
        '_purchasableIds' => $percentagePurchasables
    ],
    'allRelationships' => [
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
        '_purchasableIds' => $allPurchasables,
        '_categoryIds' => $categoryIds,
        '_userGroupIds' => ['1002']
    ]
];