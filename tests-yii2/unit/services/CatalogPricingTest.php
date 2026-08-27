<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\base\conditions\BaseCondition;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\products\ProductTypeConditionRule;
use craft\commerce\elements\conditions\purchasables\SkuConditionRule;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\db\Query;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;

/**
 * CatalogPricingTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.7
 */
class CatalogPricingTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    private array $_fixtureKeys = [
        'rad-hoodie',
        'hypercolor-tshirt',
        'double-decker-bus-toy',
    ];

    private array $_createdRules = [];

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    #[\Override]
    protected function _after()
    {
        parent::_after();

        if (empty($this->_createdRules)) {
            return;
        }

        foreach ($this->_createdRules as $rule) {
            Plugin::getInstance()->getCatalogPricingRules()->deleteCatalogPricingRuleById($rule->id);
        }
    }

    public function testGeneratePricesNoRules(): void
    {
        /** @var ProductFixture $productsFixture */
        $productsFixture = $this->tester->grabFixture('products');

        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();

        // From the product fixture 3 variants exists in all stores, 1 variant only exists in the `ukStore`
        // (3 x 3) + 1 = 10
        self::assertCount(10, new Query()->select('id')->from(Table::CATALOG_PRICING)->all());

        $checkVariantPrices = function(Product $product) {
            $storeId = $product->getStore()->id;
            $product->getVariants()->each(function(Variant $variant) use ($storeId) {
                $price = new Query()
                    ->select('price')
                    ->from(Table::CATALOG_PRICING)
                    ->where(['purchasableId' => $variant->id])
                    ->andWhere(['storeId' => $storeId])
                    ->scalar();
                self::assertEquals($variant->basePrice, $price,
                    $variant->title . ' price has been generated correctly'
                );
            });
        };

        // Check that the prices are correct
        foreach ($this->_fixtureKeys as $key) {
            $product = $productsFixture->getElement($key);
            $checkVariantPrices($product);
        }
    }

    /**
     * @return void
     * @throws \Codeception\Exception\ModuleException
     * @dataProvider generatePricesWithRulesDataProvider
     */
    public function testGeneratePricesWithRules(array $ruleConfig, int $siteId, float $rate, ?array $impactedSkus = null): void
    {
        /** @var ProductFixture $productsFixture */
        $productsFixture = $this->tester->grabFixture('products');

        $priceBySku = [];
        $variantSkus = [];

        foreach ($this->_fixtureKeys as $key) {
            $product = $productsFixture->getElement($key);
            $product->getVariants()->each(function(Variant $variant) use (&$variantSkus, &$priceBySku) {
                $priceBySku[$variant->sku] = $variant->getPrice();
                $variantSkus[] = $variant->sku;
            });
        }

        $rule = $this->_createRule($ruleConfig);

        // Generate the prices
        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
        $variants = Variant::find()->siteId($siteId)->sku($variantSkus)->collect();

        $variants->each(function(Variant $variant) use ($priceBySku, $rate, $impactedSkus) {
            // skip assertions for variants that are priced `0.00` as just in tests they are not priced in each store
            if ($variant->getPrice() === 0.00) {
                return;
            }

            if ($impactedSkus !== null && !in_array($variant->sku, $impactedSkus, true)) {
                $price = $priceBySku[$variant->sku];
            } else {
                $currency = $variant->getStore()->getCurrency();
                $price = (float)Plugin::getInstance()->getCurrencies()->getTeller($currency)->multiply($priceBySku[$variant->sku], 1 + $rate);
            }

            self::assertEquals($variant->getPrice(), $price);
        });
    }

    public function generatePricesWithRulesDataProvider(): array
    {
        return [
            'rule-no-conditions' => [
                [
                    'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
                    'name' => '10% off',
                    'enabled' => true,
                    'applyAmount' => -0.1,
                    'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
                    'isPromotionalPrice' => false,
                    'storeId' => 1,
                ],
                1,
                -0.1,
            ],
            'rule-purchasable-condition' => [
                [
                    'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
                    'name' => '20% off',
                    'enabled' => true,
                    'applyAmount' => -0.2,
                    'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
                    'isPromotionalPrice' => false,
                    'storeId' => 1,
                    '_conditionRules' => [
                        'purchasableCondition' => [
                            [
                                'class' => SkuConditionRule::class,
                                'operator' => 'bw',
                                'value' => 'rad',
                            ],
                        ],
                    ],
                ],
                1,
                -0.2,
                ['rad-hood'],
            ],
            'rule-variant-condition' => [
                [
                    'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
                    'name' => '30% off',
                    'enabled' => true,
                    'applyAmount' => -0.3,
                    'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
                    'isPromotionalPrice' => false,
                    'storeId' => 1,
                    '_conditionRules' => [
                        'variantCondition' => [
                            [
                                'class' => SkuConditionRule::class,
                                'operator' => 'ew',
                                'value' => 'hood',
                            ],
                        ],
                    ],
                ],
                1,
                -0.3,
                ['rad-hood'],
            ],
            'rule-product-condition' => [
                [
                    'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
                    'name' => '10% off',
                    'enabled' => true,
                    'applyAmount' => -0.1,
                    'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
                    'isPromotionalPrice' => false,
                    'storeId' => 1,
                    '_conditionRules' => [
                        'productCondition' => [
                            [
                                'class' => ProductTypeConditionRule::class,
                                'operator' => 'in',
                                'values' => fn(): array => [new Query()->select('uid')->from(Table::PRODUCTTYPES)->where(['handle' => 'tShirts'])->scalar()],
                            ],
                        ],
                    ],
                ],
                1,
                -0.1,
                ['hct-white', 'hct-blue'],
            ],
            'rule-product-condition-no-primary-site-store' => [
                [
                    'apply' => CatalogPricingRuleRecord::APPLY_BY_PERCENT,
                    'name' => '10% off',
                    'enabled' => true,
                    'applyAmount' => -0.1,
                    'applyPriceType' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE,
                    'isPromotionalPrice' => false,
                    'storeId' => fn() => Plugin::getInstance()->getStores()->getStoreByHandle('ukStore')->id,
                    '_conditionRules' => [
                        'productCondition' => [
                            [
                                'class' => ProductTypeConditionRule::class,
                                'operator' => 'in',
                                'values' => fn(): array => [new Query()->select('uid')->from(Table::PRODUCTTYPES)->where(['handle' => 'ukOnly'])->scalar()],
                            ],
                        ],
                    ],
                ],
                1002,
                -0.1,
                ['ddb-red'],
            ],
        ];
    }

    private function _createRule(array $config): CatalogPricingRule
    {
        $rule = new CatalogPricingRule();
        $conditionRules = [];

        if (isset($config['_conditionRules'])) {
            $conditionRules = $config['_conditionRules'];
            unset($config['_conditionRules']);
        }

        foreach ($config as $key => $item) {
            // if the item is a closure, call it to get the value
            $config[$key] = is_callable($item) ? $item() : $item;
        }

        /** @var CatalogPricingRule $rule */
        $rule = \Craft::configure($rule, $config);

        foreach ($conditionRules as $condition => $rules) {
            /** @var BaseCondition $c */
            $c = $rule->$condition;

            foreach ($rules as $r) {
                foreach ($r as $key => $item) {
                    // if the item is a closure, call it to get the value
                    $r[$key] = is_callable($item) ? $item() : $item;
                }

                $c->addConditionRule($c->createConditionRule($r));
            }

            $rule->$condition = $c;
        }

        Plugin::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($rule);

        $this->_createdRules[] = $rule;

        return $rule;
    }
}
