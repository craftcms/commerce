<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Conditions\ProductCondition;
use CraftCms\Commerce\Product\Conditions\ProductTypeConditionRule;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

function productTypeCondition(array $uids, string $operator = 'in'): ProductCondition
{
    $condition = Product::createCondition();
    $rule = new ProductTypeConditionRule();
    $rule->setValues($uids);
    $rule->operator = $operator;
    $condition->addConditionRule($rule);

    return $condition;
}

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('matchElement matches a product of the selected type', function() {
    $condition = productTypeCondition([$this->fixture->hoodiesType->uid]);

    expect($condition->matchElement($this->fixture->hoodie))->toBeTrue();
});

test('matchElement does not match a product of a different type', function() {
    $condition = productTypeCondition([$this->fixture->tShirtsType->uid]);

    expect($condition->matchElement($this->fixture->hoodie))->toBeFalse();
});

test('matchElement (not in) matches a product whose type is excluded', function() {
    $condition = productTypeCondition([$this->fixture->tShirtsType->uid], 'ni');

    expect($condition->matchElement($this->fixture->hoodie))->toBeTrue();
});

test('modifyQuery filters products by type', function() {
    $condition = productTypeCondition([$this->fixture->hoodiesType->uid]);

    $query = Product::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodie->id);
    expect($ids)->not->toContain($this->fixture->tShirt->id);
});

test('modifyQuery (not in) excludes products of the given type', function() {
    $condition = productTypeCondition([$this->fixture->hoodiesType->uid], 'ni');

    $query = Product::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->not->toContain($this->fixture->hoodie->id);
    expect($ids)->toContain($this->fixture->tShirt->id);
});
