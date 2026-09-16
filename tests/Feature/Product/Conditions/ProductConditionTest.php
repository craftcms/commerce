<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Conditions\ProductCondition;
use CraftCms\Commerce\Product\Conditions\ProductTypeConditionRule;
use CraftCms\Commerce\Product\Conditions\ProductVariantInventoryTrackedConditionRule;
use CraftCms\Commerce\Product\Conditions\ProductVariantPriceConditionRule;
use CraftCms\Commerce\Product\Conditions\ProductVariantSkuConditionRule;
use CraftCms\Commerce\Product\Conditions\ProductVariantStockConditionRule;
use CraftCms\Commerce\Product\Elements\Product;

test('createCondition returns a ProductCondition', function() {
    expect(Product::createCondition())->toBeInstanceOf(ProductCondition::class);
});

test('the condition exposes all built-in product condition rule types', function() {
    $rules = array_keys(Product::createCondition()->getSelectableConditionRules());

    expect($rules)->toContain(
        ProductTypeConditionRule::class,
        ProductVariantSkuConditionRule::class,
        ProductVariantStockConditionRule::class,
        ProductVariantInventoryTrackedConditionRule::class,
        ProductVariantPriceConditionRule::class,
    );
});
