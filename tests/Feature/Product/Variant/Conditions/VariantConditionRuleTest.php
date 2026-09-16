<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Variant\Conditions\VariantConditionRule;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use Illuminate\Database\Query\Builder;

/**
 * VariantConditionRule::modifyQuery() called $elementQuery->id() from inside modifyQuery(), which
 * was silently non-functional for the same reason as SkuConditionRule (see
 * tests/Feature/Purchasable/Conditions/SkuConditionRuleTest.php) — now fixed by calling
 * CraftCms\Cms\Element\Queries\ElementQuery::applyId() directly.
 *
 * VariantConditionRule isn't currently registered as a selectable rule on any condition, so this
 * exercises modifyQuery() directly rather than through a Condition — mirroring the where()-wrapped
 * call ElementCondition::modifyQuery() would make.
 */
beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('modifyQuery filters variants by the selected variant', function() {
    $rule = new VariantConditionRule();
    $rule->setElementIds([$this->fixture->hoodieVariant->id]);

    $query = Variant::find();
    $query->where(function(Builder $builder) use ($rule, $query) {
        $rule->modifyQuery($builder, $query);
    });
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->tShirtVariant->id);
});
