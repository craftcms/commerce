<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Product\Conditions\ProductVariantStockConditionRule;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

function productVariantStockCondition(int $value, string $operator = '<'): ElementConditionInterface
{
    $condition = Product::createCondition();
    $rule = new ProductVariantStockConditionRule();
    $rule->value = (string)$value;
    $rule->operator = $operator;
    $condition->addConditionRule($rule);

    return $condition;
}

/**
 * A variant's stock is derived from its real inventory levels ({@see \CraftCms\Commerce\Purchasable\Elements\Purchasable::getStock()}),
 * not a column that can be written to directly.
 */
function setVariantStock(Variant $variant, int $stock): void
{
    $variant->inventoryTracked = true;
    Elements::saveElement($variant);

    app(Inventory::class)->updatePurchasableInventoryLevel($variant, $stock);
}

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('matchElement matches a product with a tracked variant whose stock is below value', function() {
    setVariantStock($this->fixture->hoodieVariant, 9);
    $product = Product::find()->id($this->fixture->hoodie->id)->one();

    $condition = productVariantStockCondition(10);

    expect($condition->matchElement($product))->toBeTrue();
});

test('matchElement does not match a product whose variant stock is not below value', function() {
    setVariantStock($this->fixture->hoodieVariant, 50);
    $product = Product::find()->id($this->fixture->hoodie->id)->one();

    $condition = productVariantStockCondition(10);

    expect($condition->matchElement($product))->toBeFalse();
});

test('modifyQuery filters products by variant stock', function() {
    setVariantStock($this->fixture->hoodieVariant, 9);
    setVariantStock($this->fixture->tShirtVariant, 50);

    $condition = productVariantStockCondition(10);
    $query = Product::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->hoodie->id);
    expect($ids)->not->toContain($this->fixture->tShirt->id);
});
