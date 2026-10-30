<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule;

/**
 * `isConditionRuleSelectable()` is meant to exclude a candidate rule once another rule already
 * added to the condition reserves the same exclusive query param (e.g. only one customer rule
 * makes sense at a time). Its "existing rules" loop previously iterated `getConditionRules()`
 * (a `ConditionGroupInterface` object) with a plain `foreach`, which — since the object has no
 * public properties — silently iterated zero times, so this exclusion never actually fired.
 */
test('a rule sharing an exclusive query param with an already-added rule is excluded', function() {
    $condition = new CatalogPricingCondition();
    $condition->getConditionRules()->addRule(new CatalogPricingCustomerConditionRule());

    $selectable = $condition->getSelectableConditionRules();

    expect($selectable)->not->toHaveKey(CatalogPricingCustomerConditionRule::class);
});

test('without a conflicting rule already added, the customer rule remains selectable', function() {
    $condition = new CatalogPricingCondition();

    $selectable = $condition->getSelectableConditionRules();

    expect($selectable)->toHaveKey(CatalogPricingCustomerConditionRule::class);
});
