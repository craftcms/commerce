<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Variant\Conditions\VariantProductConditionRule;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * VariantProductConditionRule::modifyQuery() called $elementQuery->ownerId() from inside
 * modifyQuery(), which was silently non-functional for the same reason as SkuConditionRule (see
 * tests/Feature/Purchasable/Conditions/SkuConditionRuleTest.php) — now fixed by applying the
 * ownerId filter directly to $query.
 */
beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('matchElement matches a variant owned by the given product', function() {
    $rule = new VariantProductConditionRule();
    $rule->setElementIds([$this->fixture->hoodie->id]);

    expect($rule->matchElement($this->fixture->hoodieVariant))->toBeTrue();
    expect($rule->matchElement($this->fixture->tShirtVariant))->toBeFalse();
});

test('modifyQuery filters variants by owning product', function() {
    $condition = Variant::createCondition();
    $rule = new VariantProductConditionRule();
    $rule->setElementIds([$this->fixture->hoodie->id]);
    $condition->addConditionRule($rule);

    $query = Variant::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->tShirtVariant->id);
});
