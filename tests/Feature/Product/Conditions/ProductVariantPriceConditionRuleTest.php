<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Conditions\ProductCondition;
use CraftCms\Commerce\Product\Conditions\ProductVariantPriceConditionRule;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

function priceCondition(float|int $value, ?string $operator = null): ProductCondition
{
    $condition = Product::createCondition();
    $rule = new ProductVariantPriceConditionRule();
    $rule->value = (string)$value;
    if ($operator) {
        $rule->operator = $operator;
    }
    $condition->addConditionRule($rule);

    return $condition;
}

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('matchElement compares the variant price', function(float|int $price, ?string $operator, bool $expected) {
    $condition = priceCondition($price, $operator);

    expect($condition->matchElement($this->fixture->hoodie))->toBe($expected);
})->with([
    'greater than, matches' => [100, '>', true],
    'greater than, does not match' => [1000, '>', false],
    'less than, matches' => [1000, '<', true],
    'equals default operator' => [123.99, null, true],
]);

test('modifyQuery filters products by variant price', function(float|int $price, ?string $operator, bool $expectedHoodieMatch) {
    $condition = priceCondition($price, $operator);

    $query = Product::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    if ($expectedHoodieMatch) {
        expect($ids)->toContain($this->fixture->hoodie->id);
    } else {
        expect($ids)->not->toContain($this->fixture->hoodie->id);
    }
})->with([
    'greater than, matches' => [100, '>', true],
    'greater than, does not match' => [1000, '>', false],
    'less than, matches' => [1000, '<', true],
    'equals default operator' => [123.99, null, true],
]);
