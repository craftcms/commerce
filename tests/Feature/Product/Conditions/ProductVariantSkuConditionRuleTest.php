<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Conditions\ProductCondition;
use CraftCms\Commerce\Product\Conditions\ProductVariantSkuConditionRule;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

function skuCondition(string $value, string $operator = '='): ProductCondition
{
    $condition = Product::createCondition();
    $rule = new ProductVariantSkuConditionRule();
    $rule->value = $value;
    $rule->operator = $operator;
    $condition->addConditionRule($rule);

    return $condition;
}

test('matchElement matches a product with a variant of the given sku', function() {
    $condition = skuCondition('rad-hood');

    expect($condition->matchElement($this->fixture->hoodie))->toBeTrue();
});

test('matchElement does not match a product without a variant of the given sku', function() {
    $condition = skuCondition('does-not-exist');

    expect($condition->matchElement($this->fixture->hoodie))->toBeFalse();
});

test('modifyQuery filters products by variant sku prefix', function() {
    $condition = skuCondition('rad', 'bw');

    $query = Product::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodie->id);
    expect($ids)->not->toContain($this->fixture->tShirt->id);
});
