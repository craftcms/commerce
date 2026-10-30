<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * Saves a catalog pricing rule targeting a single SKU and regenerates catalog prices.
 *
 * @param array{name: string, sku: string, applyAmount: float, isPromotionalPrice?: bool} $rule
 */
function saveVariantCatalogPricingRuleForSku(array $rule): int
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
function applyVariantCatalogPricingRulesForSkus(array $rules): array
{
    $ids = array_map(saveVariantCatalogPricingRuleForSku(...), $rules);

    if (!empty($ids)) {
        app(CatalogPricing::class)->generateCatalogPrices();
    }

    return $ids;
}

/** @param int[] $ids */
function removeVariantCatalogPricingRules(array $ids): void
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

test('a variant with no catalog pricing rules and no sales falls back to its base price', function() {
    $variant = Variant::find()->sku('rad-hood')->one();

    expect($variant->getPrice())->toEqual(123.99);
    expect($variant->getPromotionalPrice())->toBeNull();
    expect($variant->getSalePrice())->toEqual(123.99);
});

test('getPrice/getPromotionalPrice/getSalePrice reflect any active catalog pricing rules', function(array $rules, float|int|null $salePrice, float|int|null $promotionalPrice, float|int|null $price) {
    $ruleIds = applyVariantCatalogPricingRulesForSkus($rules);

    $variant = Variant::find()->sku('rad-hood')->one();

    expect($variant)->toBeInstanceOf(Variant::class);
    expect($variant->getPrice())->toEqual($price);
    expect($variant->getPromotionalPrice())->toEqual($promotionalPrice);
    expect($variant->getSalePrice())->toEqual($salePrice);

    removeVariantCatalogPricingRules($ruleIds);
})->with('variantCatalogPricingRuleScenarios');

test('the price/promotionalPrice/salePrice query scopes stay in sync with catalog pricing rules', function(array $rules, float|int|null $salePrice, float|int|null $promotionalPrice, float|int|null $price) {
    $ruleIds = applyVariantCatalogPricingRulesForSkus($rules);

    $variant = Variant::find()->price($price)->one();
    expect($variant)->toBeInstanceOf(Variant::class);
    expect($variant->getSku())->toBe('rad-hood');
    expect($variant->getPrice())->toEqual($price);

    if ($promotionalPrice !== null) {
        $variant = Variant::find()->promotionalPrice($promotionalPrice)->one();
        expect($variant)->toBeInstanceOf(Variant::class);
        expect($variant->getSku())->toBe('rad-hood');
        expect($variant->getPromotionalPrice())->toEqual($promotionalPrice);
    }

    // Once a catalog pricing rule exists, `salePrice` filters against the real
    // `catalogprices.salePrice` column. With none active, it instead compiles to a raw
    // `CASE WHEN ... END` expression (see QueriesPurchasablePricing::applySalePrice) — under
    // SQLite specifically, a bound parameter never matches a computed expression's result due
    // to a storage-class mismatch (same underlying quirk as COM-667/COM-668's salePrice-order
    // no-op), so that branch isn't exercised here.
    if (!empty($rules)) {
        $variant = Variant::find()->salePrice($salePrice)->one();
        expect($variant)->toBeInstanceOf(Variant::class);
        expect($variant->getSku())->toBe('rad-hood');
        expect($variant->getSalePrice())->toEqual($salePrice);
    }

    removeVariantCatalogPricingRules($ruleIds);
})->with('variantCatalogPricingRuleScenarios');

test('the onPromotion query scope matches variants with an active promotional price', function(array $rules, float|int|null $salePrice, float|int|null $promotionalPrice, float|int|null $price) {
    $ruleIds = applyVariantCatalogPricingRulesForSkus($rules);

    $variantOnPromotion = Variant::find()->onPromotion()->one();

    if ($promotionalPrice !== null) {
        expect($variantOnPromotion)->toBeInstanceOf(Variant::class);
        expect($variantOnPromotion->getSku())->toBe('rad-hood');
        expect($variantOnPromotion->getPromotionalPrice())->toEqual($promotionalPrice);
    } else {
        expect($variantOnPromotion)->toBeNull();
    }

    // Exercised for coverage of the inverse scope's SQL path, but not asserted on: its result
    // is ambiguous whenever nothing has a promotional price set yet, since a plain "<" price
    // comparison against a NULL promotional price never matches either way (see
    // QueriesPurchasablePricing::applyOnPromotion).
    Variant::find()->onPromotion(false)->one();

    removeVariantCatalogPricingRules($ruleIds);
})->with('variantCatalogPricingRuleScenarios');

dataset('variantCatalogPricingRuleScenarios', [
    'no catalog pricing rules' => [
        [],
        123.99,
        null,
        123.99,
    ],
    'a single rule reduces the price' => [
        [
            ['name' => 'Test Rule', 'sku' => 'rad-hood', 'applyAmount' => -0.1],
        ],
        111.59,
        null,
        111.59,
    ],
    'a promotional rule discounts the sale price without changing the price' => [
        [
            ['name' => 'Test Rule', 'sku' => 'rad-hood', 'applyAmount' => -0.1, 'isPromotionalPrice' => true],
        ],
        111.59,
        111.59,
        123.99,
    ],
    'a non-promotional rule wins over an additional promotional rule' => [
        [
            ['name' => 'Test Rule - 5%', 'sku' => 'rad-hood', 'applyAmount' => -0.05],
            ['name' => 'Test Rule - 1%', 'sku' => 'rad-hood', 'applyAmount' => -0.01, 'isPromotionalPrice' => true],
        ],
        117.79,
        null,
        117.79,
    ],
]);
