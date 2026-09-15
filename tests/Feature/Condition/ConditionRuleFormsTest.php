<?php

declare(strict_types=1);

use CraftCms\Cms\Condition\ConditionBuilderRenderer;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Commerce\Address\Conditions\DiscountAddressCondition;
use CraftCms\Commerce\Address\Conditions\PostalCodeFormulaConditionRule;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition;
use CraftCms\Commerce\Customer\Conditions\HasOrdersConditionRule;
use CraftCms\Commerce\Order\Conditions\ContainsPurchasablesConditionRule;
use CraftCms\Commerce\Order\Conditions\CustomerConditionRule;
use CraftCms\Commerce\Order\Conditions\HasPurchasableConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule;
use CraftCms\Commerce\Product\Variant\Conditions\VariantProductConditionRule;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCategoryConditionRule;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCondition;

/**
 * Rendering-level smoke test for every condition rule whose `inputHtml()`/`elementSelectConfig()`
 * was ported to the Form API (`getForm()`/`inputNodes()`) after cms-6 PR #19588 removed the old
 * methods. phpstan can't catch a wrong Form API call (missing required setter, bad control usage)
 * since these are all fluent calls on concrete classes — only rendering the actual HTML surfaces
 * that, so this exercises the same `ConditionBuilderRenderer` path the CP uses.
 */
function renderCondition(\CraftCms\Cms\Condition\BaseCondition $condition): string
{
    $condition->mainTag = 'div';
    $condition->name = 'condition';

    return new ConditionBuilderRenderer($condition)->render();
}

test('OrderCondition rules with ported Form API inputs render without error', function() {
    /** @var OrderCondition $condition */
    $condition = Conditions::createCondition(['class' => OrderCondition::class]);
    $condition->addConditionRule(Conditions::createConditionRule(['class' => CustomerConditionRule::class]));
    $condition->addConditionRule(Conditions::createConditionRule(['class' => HasPurchasableConditionRule::class]));
    $condition->addConditionRule(Conditions::createConditionRule(['class' => ContainsPurchasablesConditionRule::class]));
    $condition->addConditionRule(Conditions::createConditionRule(['class' => TotalPriceConditionRule::class]));

    expect(renderCondition($condition))->toBeString()->not->toBe('');
});

test('CatalogPricingCondition rules with ported Form API inputs render without error', function() {
    /** @var CatalogPricingCondition $condition */
    $condition = Conditions::createCondition(['class' => CatalogPricingCondition::class]);
    $condition->addConditionRule(Conditions::createConditionRule(['class' => CatalogPricingCustomerConditionRule::class]));
    $condition->addConditionRule(Conditions::createConditionRule(['class' => CatalogPricingPurchasableConditionRule::class]));

    expect(renderCondition($condition))->toBeString()->not->toBe('');
});

test('PostalCodeFormulaConditionRule renders without error', function() {
    /** @var DiscountAddressCondition $condition */
    $condition = Conditions::createCondition(['class' => DiscountAddressCondition::class]);
    $condition->addConditionRule(Conditions::createConditionRule(['class' => PostalCodeFormulaConditionRule::class]));

    expect(renderCondition($condition))->toBeString()->not->toBe('');
});

test('CatalogPricingRulePurchasableCategoryConditionRule renders without error', function() {
    /** @var CatalogPricingRulePurchasableCondition $condition */
    $condition = Conditions::createCondition(['class' => CatalogPricingRulePurchasableCondition::class]);
    $condition->addConditionRule(Conditions::createConditionRule(['class' => CatalogPricingRulePurchasableCategoryConditionRule::class]));

    expect(renderCondition($condition))->toBeString()->not->toBe('');
});

test('HasOrdersConditionRule (with its nested order-condition builder) renders without error', function() {
    /** @var DiscountCustomerCondition $condition */
    $condition = Conditions::createCondition(['class' => DiscountCustomerCondition::class]);
    $condition->addConditionRule(Conditions::createConditionRule(['class' => HasOrdersConditionRule::class]));

    expect(renderCondition($condition))->toBeString()->not->toBe('');
});

test('VariantProductConditionRule renders without error', function() {
    /** @var CatalogPricingRuleVariantCondition $condition */
    $condition = Conditions::createCondition(['class' => CatalogPricingRuleVariantCondition::class]);
    $condition->addConditionRule(Conditions::createConditionRule(['class' => VariantProductConditionRule::class]));

    expect(renderCondition($condition))->toBeString()->not->toBe('');
});
