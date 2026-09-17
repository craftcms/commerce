<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Queries\VariantQuery;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;

beforeEach(function() {
    $this->fixture = VariantQueryFixture::seed();
});

test('find returns a VariantQuery', function() {
    expect(Variant::find())->toBeInstanceOf(VariantQuery::class);
});

test('shippingCategoryId filters to variants with the given shipping category', function() {
    $ids = Variant::find()->shippingCategoryId($this->fixture->specialShippingCategory->id)->ids();

    expect($ids)->toContain($this->fixture->whiteVariant->id, $this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->blueVariant->id);
});

test('shippingCategory filters by handle', function() {
    $ids = Variant::find()->shippingCategory('specialShipping')->ids();

    expect($ids)->toContain($this->fixture->whiteVariant->id, $this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->blueVariant->id);
});

test('shippingCategory filters by a ShippingCategory instance', function() {
    $ids = Variant::find()->shippingCategory($this->fixture->specialShippingCategory)->ids();

    expect($ids)->toContain($this->fixture->whiteVariant->id);
    expect($ids)->not->toContain($this->fixture->blueVariant->id);
});

test('taxCategoryId filters to variants with the given tax category', function() {
    $ids = Variant::find()->taxCategoryId($this->fixture->reducedTaxCategory->id)->ids();

    expect($ids)->toContain($this->fixture->whiteVariant->id);
    expect($ids)->not->toContain($this->fixture->blueVariant->id, $this->fixture->hoodieVariant->id);
});

test('taxCategory filters by handle', function() {
    $ids = Variant::find()->taxCategory('reducedTax')->ids();

    expect($ids)->toContain($this->fixture->whiteVariant->id);
    expect($ids)->not->toContain($this->fixture->blueVariant->id, $this->fixture->hoodieVariant->id);
});

test('taxCategory filters by a TaxCategory instance', function() {
    $ids = Variant::find()->taxCategory($this->fixture->reducedTaxCategory)->ids();

    expect($ids)->toContain($this->fixture->whiteVariant->id);
    expect($ids)->not->toContain($this->fixture->blueVariant->id);
});

test('every variant has its price and salePrice populated', function() {
    $results = Variant::find()->all();

    expect($results)->not->toBeEmpty();
    foreach ($results as $variant) {
        expect($variant->getPrice())->not->toBeNull();
        expect($variant->getSalePrice())->not->toBeNull();
    }
});

test('orderBy sorts variants by price', function(string $orderBy, bool $descending) {
    $skus = Variant::find()->orderBy($orderBy)->collect()->map(fn(Variant $v) => $v->getSku())->all();
    $expected = ['hct-white', 'hct-blue', 'rad-hood'];

    expect($skus)->toBe($descending ? array_reverse($expected) : $expected);
})->with([
    'price asc' => ['price ASC', false],
    'price desc' => ['price DESC', true],
]);

test('a catalog pricing rule targeting a sku updates that variant\'s price and salePrice', function() {
    $storeId = app(Stores::class)->getPrimaryStore()->id;

    $catalogPricingRule = new CatalogPricingRule();
    $catalogPricingRule->storeId = $storeId;
    $catalogPricingRule->name = 'Half off blue';
    $catalogPricingRule->apply = CatalogPricingRuleRecord::APPLY_BY_PERCENT;
    $catalogPricingRule->applyAmount = 50 / -100;
    $catalogPricingRule->applyPriceType = CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE;
    $catalogPricingRule->enabled = true;

    $variantCondition = $catalogPricingRule->getVariantCondition();
    $rule = new SkuConditionRule();
    $rule->value = 'hct-blue';
    $variantCondition->addConditionRule($rule);
    $catalogPricingRule->setVariantCondition($variantCondition);

    expect(app(CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule))->toBeTrue();

    app(CatalogPricing::class)->generateCatalogPrices();

    $blue = Variant::find()->sku('hct-blue')->one();
    expect($blue->getPrice())->toEqual(11.0);
    expect($blue->getSalePrice())->toEqual(11.0);

    $white = Variant::find()->sku('hct-white')->one();
    expect($white->getPrice())->toEqual(19.99);
});
