<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Variant\Conditions\VariantCondition;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * SkuConditionRule::modifyQuery() called $elementQuery->sku() from inside modifyQuery(), which
 * was silently non-functional until PurchasableQuery moved its sku/stock/etc scope filters into
 * CraftCms\Commerce\Purchasable\Queries\Concerns\QueriesPurchasableAttributes — the constructor's
 * own beforeQuery() callback always ran first and never saw the value set that late.
 */
function purchasableSkuCondition(string $value): VariantCondition
{
    $condition = Variant::createCondition();
    $rule = new SkuConditionRule();
    $rule->value = $value;
    $condition->addConditionRule($rule);

    return $condition;
}

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('matchElement matches a variant with the given sku', function() {
    $condition = purchasableSkuCondition($this->fixture->hoodieVariant->getSku());

    expect($condition->matchElement($this->fixture->hoodieVariant))->toBeTrue();
});

test('modifyQuery filters variants by sku', function() {
    $condition = purchasableSkuCondition($this->fixture->hoodieVariant->getSku());

    $query = Variant::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->tShirtVariant->id);
});
