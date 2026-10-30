<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCondition;
use CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * PurchasableConditionRule::modifyQuery() called $elementQuery->id() from inside modifyQuery(),
 * which was silently non-functional for the same reason as SkuConditionRule (see
 * tests/Feature/Purchasable/Conditions/SkuConditionRuleTest.php) — now fixed by calling
 * CraftCms\Cms\Element\Queries\ElementQuery::applyId() directly.
 */
beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('modifyQuery filters variants by the selected purchasable ids', function() {
    $rule = new PurchasableConditionRule();
    $rule->setElementIds([Variant::class => [$this->fixture->hoodieVariant->id]]);

    $condition = new CatalogPricingRulePurchasableCondition(Variant::class);
    $condition->addConditionRule($rule);

    $query = Variant::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->tShirtVariant->id);
});

test('matchElement matches an element with a selected id', function() {
    $rule = new PurchasableConditionRule();
    $rule->setElementIds([Variant::class => [$this->fixture->hoodieVariant->id]]);

    expect($rule->matchElement($this->fixture->hoodieVariant))->toBeTrue();
    expect($rule->matchElement($this->fixture->tShirtVariant))->toBeFalse();
});
