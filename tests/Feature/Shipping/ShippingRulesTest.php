<?php

declare(strict_types=1);

use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Shipping\Data\ShippingMethod;
use CraftCms\Commerce\Shipping\Data\ShippingRule;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Shipping\ShippingMethods;
use CraftCms\Commerce\Shipping\ShippingRuleCategories;
use CraftCms\Commerce\Shipping\ShippingRules;
use CraftCms\Commerce\Store\Stores;

/**
 * Seeds a shipping method with two rules on the primary store, which (via
 * ShippingRules::saveShippingRule()) each get one ShippingRuleCategory row per existing shipping
 * category — the store's default "General" category plus a second one created here — giving
 * every rule two categories to assert against.
 *
 * @return int[] the two saved shipping rule IDs
 */
function seedShippingRulesWithCategories(): array
{
    $storeId = app(Stores::class)->getPrimaryStore()->id;

    $extraCategory = new ShippingCategory();
    $extraCategory->storeId = $storeId;
    $extraCategory->name = 'Extra Category';
    $extraCategory->handle = 'extraCategory';
    if (!app(ShippingCategories::class)->saveShippingCategory($extraCategory)) {
        throw new RuntimeException('Could not save shipping category: ' . json_encode($extraCategory->errors()->all()));
    }

    $method = new ShippingMethod();
    $method->storeId = $storeId;
    $method->name = 'Test Shipping';
    $method->handle = 'testShipping';
    $method->enabled = true;
    if (!app(ShippingMethods::class)->saveShippingMethod($method)) {
        throw new RuntimeException('Could not save shipping method: ' . json_encode($method->errors()->all()));
    }

    $ruleIds = [];
    foreach (['Rule One', 'Rule Two'] as $name) {
        $rule = new ShippingRule();
        $rule->storeId = $storeId;
        $rule->methodId = $method->id;
        $rule->name = $name;
        if (!app(ShippingRules::class)->saveShippingRule($rule)) {
            throw new RuntimeException('Could not save shipping rule: ' . json_encode($rule->errors()->all()));
        }
        $ruleIds[] = $rule->id;
    }

    return $ruleIds;
}

test('getShippingRuleCategoriesByRuleIds returns categories indexed by rule ID then category ID', function() {
    $ruleIds = seedShippingRulesWithCategories();

    $categoriesByRuleId = app(ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleIds($ruleIds);

    expect(array_keys($categoriesByRuleId))->toEqualCanonicalizing($ruleIds);

    foreach ($categoriesByRuleId as $ruleId => $categories) {
        expect($ruleIds)->toContain($ruleId)
            ->and($categories)->toHaveCount(2);

        foreach ($categories as $categoryId => $ruleCategory) {
            expect($ruleCategory->shippingCategoryId)->toBe($categoryId);
        }
    }
});

test('getShippingRuleCategoriesByRuleIds returns an empty array for an empty input', function() {
    $result = app(ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleIds([]);

    expect($result)->toBe([]);
});

test('getAllShippingRules eager loads each rule\'s shipping rule categories', function() {
    $ruleIds = seedShippingRulesWithCategories();

    $rules = app(ShippingRules::class)->getAllShippingRules()->whereIn('id', $ruleIds);

    expect($rules)->toHaveCount(2);

    foreach ($rules as $rule) {
        expect($rule->getShippingRuleCategories())->toHaveCount(2);
    }
});

test('bulk fetching shipping rule categories matches fetching them one rule at a time', function() {
    $ruleIds = seedShippingRulesWithCategories();

    $bulkCategories = app(ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleIds($ruleIds);

    foreach ($ruleIds as $ruleId) {
        $singleCategories = app(ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleId($ruleId);
        $bulkForRule = $bulkCategories[$ruleId] ?? [];

        expect(array_keys($singleCategories))->toEqualCanonicalizing(array_keys($bulkForRule));
    }
});
