<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * Saves a catalog pricing rule targeting a single SKU and regenerates catalog prices.
 *
 * @param array{name: string, sku: string, applyAmount: float, isPromotionalPrice?: bool} $rule
 */
function saveCatalogPricingRuleForSku(array $rule): int
{
    $catalogPricingRule = new CatalogPricingRule();
    $catalogPricingRule->storeId = app(Stores::class)->getPrimaryStore()->id;
    $catalogPricingRule->name = $rule['name'];
    $catalogPricingRule->applyAmount = $rule['applyAmount'];
    $catalogPricingRule->isPromotionalPrice = $rule['isPromotionalPrice'] ?? false;
    $catalogPricingRule->enabled = true;

    /** @var CatalogPricingRuleVariantCondition $variantCondition */
    $variantCondition = $catalogPricingRule->getVariantCondition();
    $skuRule = new SkuConditionRule();
    $skuRule->value = $rule['sku'];
    $variantCondition->addConditionRule($skuRule);
    $catalogPricingRule->setVariantCondition($variantCondition);

    expect(app(CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule))->toBeTrue();

    return $catalogPricingRule->id;
}

/**
 * @param array<int, array{name: string, sku: string, applyAmount: float, isPromotionalPrice?: bool}> $rules
 * @return int[] The saved rules' IDs.
 */
function applyCatalogPricingRulesForSkus(array $rules): array
{
    $ids = array_map(saveCatalogPricingRuleForSku(...), $rules);

    if (!empty($ids)) {
        app(CatalogPricing::class)->generateCatalogPrices();
    }

    return $ids;
}

/** @param int[] $ids */
function removeCatalogPricingRules(array $ids): void
{
    if (empty($ids)) {
        return;
    }

    foreach ($ids as $id) {
        app(CatalogPricingRules::class)->deleteCatalogPricingRuleById($id);
    }

    app(CatalogPricing::class)->generateCatalogPrices();
}

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('getDefaultPrice reflects any active catalog pricing rules for the default variant', function(array $rules, float $expectedPrice) {
    $ruleIds = applyCatalogPricingRulesForSkus($rules);

    $product = Product::find()->defaultSku('rad-hood')->one();

    expect($product)->toBeInstanceOf(Product::class);
    expect($product->getDefaultPrice())->toEqual($expectedPrice);

    removeCatalogPricingRules($ruleIds);
})->with('catalogPricingRuleScenarios');

test('the defaultPrice and defaultSku query scopes stay in sync with the default variant, even with catalog pricing active', function(array $rules, float $expectedPrice) {
    $ruleIds = applyCatalogPricingRulesForSkus($rules);

    $product = Product::find()->defaultPrice($expectedPrice)->one();
    $variant = $product->getDefaultVariant();

    expect($product)->toBeInstanceOf(Product::class);
    expect($variant)->toBeInstanceOf(Variant::class);
    expect($product->defaultSku)->toBe('rad-hood');
    expect($variant->getSku())->toBe('rad-hood');
    expect($product->defaultPrice)->toEqual($expectedPrice);
    expect($variant->getPrice())->toEqual($expectedPrice);

    removeCatalogPricingRules($ruleIds);
})->with('catalogPricingRuleScenarios');

test('orderBy(defaultPrice) sorts products by their catalog price, not just their base price', function(array $rules) {
    $ruleIds = applyCatalogPricingRulesForSkus($rules);

    $ascending = Product::find()->orderBy(['defaultPrice' => SORT_ASC])->all();
    $price = null;
    foreach ($ascending as $product) {
        expect($product->getDefaultPrice())->toBeGreaterThanOrEqual($price ?? -INF);
        $price = $product->getDefaultPrice();
    }

    $descending = Product::find()->orderBy(['defaultPrice' => SORT_DESC])->all();
    $price = null;
    foreach ($descending as $product) {
        expect($product->getDefaultPrice())->toBeLessThanOrEqual($price ?? INF);
        $price = $product->getDefaultPrice();
    }

    removeCatalogPricingRules($ruleIds);
})->with('catalogPricingRuleScenarios');

dataset('catalogPricingRuleScenarios', [
    'no catalog pricing rules' => [
        [],
        123.99,
    ],
    'a single rule reduces the price' => [
        [
            ['name' => 'Test Rule', 'sku' => 'rad-hood', 'applyAmount' => -0.1],
        ],
        111.59,
    ],
    'a promotional rule does not affect the default price' => [
        [
            ['name' => 'Test Rule', 'sku' => 'rad-hood', 'applyAmount' => -0.1, 'isPromotionalPrice' => true],
        ],
        123.99,
    ],
    'a non-promotional rule wins over an additional promotional rule' => [
        [
            ['name' => 'Test Rule - 5%', 'sku' => 'rad-hood', 'applyAmount' => -0.05],
            ['name' => 'Test Rule - 1%', 'sku' => 'rad-hood', 'applyAmount' => -0.01, 'isPromotionalPrice' => true],
        ],
        117.79,
    ],
]);
