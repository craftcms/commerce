<?php

declare(strict_types=1);

use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule as CatalogPricingRuleData;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Product\Conditions\ProductTypeConditionRule;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use CraftCms\Commerce\Tests\Support\CatalogPricingFixture;
use Illuminate\Support\Facades\DB;

/**
 * @param array<string, object> $conditionRules Keyed by condition property name
 *   ("purchasableCondition", "variantCondition", "productCondition"), each a single
 *   pre-configured condition rule instance to add to that condition.
 */
function createCatalogPricingRule(array $attributes, array $conditionRules = []): CatalogPricingRuleData
{
    $rule = new CatalogPricingRuleData($attributes);

    foreach ($conditionRules as $property => $conditionRule) {
        $getter = 'get' . ucfirst($property);
        $setter = 'set' . ucfirst($property);
        $condition = $rule->$getter();
        $condition->addConditionRule($conditionRule);
        $rule->$setter($condition);
    }

    if (!app(CatalogPricingRules::class)->saveCatalogPricingRule($rule)) {
        throw new RuntimeException('Could not save catalog pricing rule: ' . json_encode($rule->errors()->all()));
    }

    return $rule;
}

test('generateCatalogPrices with no rules copies each purchasable\'s own store base price into catalog pricing', function() {
    $fixture = CatalogPricingFixture::seed();

    app(CatalogPricing::class)->generateCatalogPrices();

    // 3 variants exist in every store (3 x 3), plus 1 UK-only variant that only exists in the UK store.
    expect(DB::table(Table::CATALOG_PRICING)->count())->toBe(10);

    foreach ([$fixture->radHood, $fixture->hctWhite, $fixture->hctBlue, $fixture->ddbRed] as $variant) {
        $price = DB::table(Table::CATALOG_PRICING)
            ->where('purchasableId', $variant->id)
            ->where('storeId', $variant->getStore()->id)
            ->value('price');

        expect((float) $price)->toBe($variant->basePrice);
    }
});

test('generateCatalogPrices applies a catalog pricing rule to matching variant prices', function(array $ruleAttributes, array $conditionRules, string $siteHandle, float $rate, ?array $impactedSkus) {
    $fixture = CatalogPricingFixture::seed();
    $sites = $fixture->stores;

    $siteId = match ($siteHandle) {
        'usSite' => $sites->usSite->id,
        'ukSite' => $sites->ukSite->id,
    };
    $storeId = match ($ruleAttributes['storeId']) {
        'primaryStore' => $sites->primaryStore->id,
        'ukStore' => $sites->ukStore->id,
    };
    $ruleAttributes['storeId'] = $storeId;

    $conditionRuleInstances = [];
    foreach ($conditionRules as $property => $factory) {
        $conditionRuleInstances[$property] = $factory($fixture);
    }

    $allSkus = ['rad-hood', 'hct-white', 'hct-blue', 'ddb-red'];
    $priceBySku = Variant::find()->siteId($siteId)->sku($allSkus)->collect()
        ->mapWithKeys(fn(Variant $variant) => [$variant->sku => $variant->getPrice()]);

    createCatalogPricingRule($ruleAttributes, $conditionRuleInstances);

    app(CatalogPricing::class)->generateCatalogPrices();

    $variants = Variant::find()->siteId($siteId)->sku($allSkus)->collect();

    $variants->each(function(Variant $variant) use ($priceBySku, $rate, $impactedSkus) {
        // Variants that don't exist for this site's store hold a `0.00` base price there — nothing
        // to assert against.
        if ($variant->getPrice() === 0.00) {
            return;
        }

        $originalPrice = $priceBySku[$variant->sku];

        if ($impactedSkus !== null && !in_array($variant->sku, $impactedSkus, true)) {
            $expectedPrice = $originalPrice;
        } else {
            $currency = $variant->getStore()->getCurrency();
            $expectedPrice = (float) app(Currencies::class)->getTeller($currency)->multiply($originalPrice, 1 + $rate);
        }

        expect($variant->getPrice())->toBe($expectedPrice);
    });
})->with([
    'rule with no conditions applies to every variant in the store' => [
        [
            'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
            'name' => '10% off',
            'enabled' => true,
            'applyAmount' => -0.1,
            'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
            'isPromotionalPrice' => false,
            'storeId' => 'primaryStore',
        ],
        [],
        'usSite',
        -0.1,
        null,
    ],
    'rule with a variant SKU condition only applies to matching variants' => [
        [
            'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
            'name' => '30% off',
            'enabled' => true,
            'applyAmount' => -0.3,
            'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
            'isPromotionalPrice' => false,
            'storeId' => 'primaryStore',
        ],
        [
            'variantCondition' => fn() => tap(new SkuConditionRule(), function(SkuConditionRule $rule) {
                $rule->operator = 'ew';
                $rule->value = 'hood';
            }),
        ],
        'usSite',
        -0.3,
        ['rad-hood'],
    ],
    'rule with a product type condition only applies to matching products' => [
        [
            'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
            'name' => '10% off',
            'enabled' => true,
            'applyAmount' => -0.1,
            'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
            'isPromotionalPrice' => false,
            'storeId' => 'primaryStore',
        ],
        [
            'productCondition' => fn(CatalogPricingFixture $fixture) => tap(new ProductTypeConditionRule(), function(ProductTypeConditionRule $rule) use ($fixture) {
                $rule->setValues([$fixture->tShirtsType->uid]);
            }),
        ],
        'usSite',
        -0.1,
        ['hct-white', 'hct-blue'],
    ],
    'rule scoped to a non-primary store still applies to that store\'s products' => [
        [
            'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
            'name' => '10% off',
            'enabled' => true,
            'applyAmount' => -0.1,
            'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
            'isPromotionalPrice' => false,
            'storeId' => 'ukStore',
        ],
        [
            'productCondition' => fn(CatalogPricingFixture $fixture) => tap(new ProductTypeConditionRule(), function(ProductTypeConditionRule $rule) use ($fixture) {
                $rule->setValues([$fixture->ukOnlyType->uid]);
            }),
        ],
        'ukSite',
        -0.1,
        ['ddb-red'],
    ],
]);
