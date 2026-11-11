<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Queries\VariantQuery;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;
use Illuminate\Support\Facades\DB;

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

test('orderBy sorts variants by salePrice', function(string $column, string $direction, array $expected) {
    // A promotional price on the most expensive variant makes it the cheapest by sale price, so a
    // sort that silently does nothing can't pass.
    DB::table(Table::PURCHASABLES_STORES)
        ->where('purchasableId', $this->fixture->hoodieVariant->id)
        ->update(['basePromotionalPrice' => 1]);

    $skus = Variant::find()->orderBy($column, $direction)->collect()->map(fn(Variant $v) => $v->getSku())->all();

    expect($skus)->toBe($expected);
})->with([
    'salePrice asc' => ['salePrice', 'asc', ['rad-hood', 'hct-white', 'hct-blue']],
    'salePrice desc' => ['salePrice', 'desc', ['hct-blue', 'hct-white', 'rad-hood']],
]);

test('orderBy sorts variants by promotionalPrice', function() {
    foreach (['whiteVariant' => 15, 'blueVariant' => 2, 'hoodieVariant' => 1] as $variant => $promotionalPrice) {
        DB::table(Table::PURCHASABLES_STORES)
            ->where('purchasableId', $this->fixture->$variant->id)
            ->update(['basePromotionalPrice' => $promotionalPrice]);
    }

    $skus = Variant::find()->orderBy('promotionalPrice')->collect()->map(fn(Variant $v) => $v->getSku())->all();

    expect($skus)->toBe(['rad-hood', 'hct-blue', 'hct-white']);
});

test('orderBy sorts variants by salePrice from catalog pricing', function() {
    $catalogPricingRule = new CatalogPricingRule();
    $catalogPricingRule->storeId = app(Stores::class)->getPrimaryStore()->id;
    $catalogPricingRule->name = 'Hoodie for a dollar';
    $catalogPricingRule->apply = CatalogPricingRuleRecord::APPLY_TO_FLAT;
    $catalogPricingRule->applyAmount = 1;
    $catalogPricingRule->applyPriceType = CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE;
    $catalogPricingRule->isPromotionalPrice = true;
    $catalogPricingRule->enabled = true;

    $variantCondition = $catalogPricingRule->getVariantCondition();
    $rule = new SkuConditionRule();
    $rule->value = 'rad-hood';
    $variantCondition->addConditionRule($rule);
    $catalogPricingRule->setVariantCondition($variantCondition);

    expect(app(CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule))->toBeTrue();
    app(CatalogPricing::class)->generateCatalogPrices();

    $skus = fn(string $direction) => Variant::find()->orderBy('salePrice', $direction)->collect()->map(fn(Variant $v) => $v->getSku())->all();

    expect($skus('asc'))->toBe(['rad-hood', 'hct-white', 'hct-blue'])
        ->and($skus('desc'))->toBe(['hct-blue', 'hct-white', 'rad-hood']);
});

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

test('isDefault derives from the product\'s defaultVariantId', function() {
    $product = $this->fixture->tee;
    $white = $this->fixture->whiteVariant;
    $blue = $this->fixture->blueVariant;

    DB::table(Table::PRODUCTS)->where('id', $product->id)->update(['defaultVariantId' => $white->id]);

    expect(Variant::find()->productId($product->id)->isDefault(true)->ids())->toBe([$white->id])
        ->and(Variant::find()->productId($product->id)->isDefault(false)->ids())->toBe([$blue->id]);

    DB::table(Table::PRODUCTS)->where('id', $product->id)->update(['defaultVariantId' => $blue->id]);

    expect(Variant::find()->productId($product->id)->isDefault(true)->ids())->toBe([$blue->id])
        ->and(Variant::find()->productId($product->id)->isDefault(false)->ids())->toBe([$white->id]);

    DB::table(Table::PRODUCTS)->where('id', $product->id)->update(['defaultVariantId' => null]);

    expect(Variant::find()->productId($product->id)->isDefault(true)->ids())->toBe([])
        ->and(Variant::find()->productId($product->id)->isDefault(false)->ids())->toContain($white->id, $blue->id);
});
