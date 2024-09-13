<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\conditions\purchasables\SkuConditionRule;
use craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\commerce\services\CatalogPricingRules;
use craft\commerce\services\Sales;
use craftcommercetests\fixtures\ProductFixture;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * PricingCatalogTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PricingCatalogTest extends Unit
{
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

    /**
     * @return void
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function testVariantPricing(): void
    {
        $variant = Variant::find()->sku('rad-hood')->one();

        Plugin::getInstance()->set('catalogPricingRules', $this->make(CatalogPricingRules::class, [
            'canUseCatalogPricingRules' => function() {
                self::atLeastOnce();
                return true;
            },
        ]));

        Plugin::getInstance()->set('sales', $this->make(Sales::class, [
            'getAllSales' => function() {
                self::never();
                return [];
            },
        ]));

        self::assertEquals(123.99, $variant->getPrice());
        self::assertEquals(null, $variant->getPromotionalPrice());
        self::assertEquals(123.99, $variant->getSalePrice());
    }

    /**
     * @param string $sku
     * @param array|null $rules
     * @param float|int|null $salePrice
     * @param float|int|null $promotionalPrice
     * @param float|int|null $price
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     * @dataProvider variantCatalogPricesDataProvider
     * @since 5.1.0
     */
    public function testVariantCatalogPrices(string $sku, ?array $rules, float|int|null $salePrice, float|int|null $promotionalPrice, float|int|null $price): void
    {
        $catalogPricingRules = [];

        if (!empty($rules)) {
            foreach ($rules as $rule) {
                $catalogPricingRule = Craft::createObject($rule);
                Plugin::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule);
                $catalogPricingRules[] = $catalogPricingRule->id;
            }

            Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
        }

        $variant = Variant::find()->sku($sku)->one();

        self::assertInstanceof(Variant::class, $variant);
        self::assertEquals($price, $variant->getPrice());
        self::assertEquals($promotionalPrice, $variant->getPromotionalPrice());
        self::assertEquals($salePrice, $variant->getSalePrice());

        // Tidy up at the end of the test
        if (!empty($catalogPricingRules)) {
            foreach ($catalogPricingRules as $catalogPricingRule) {
                Plugin::getInstance()->getCatalogPricingRules()->deleteCatalogPricingRuleById($catalogPricingRule);
            }

            Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
        }
    }

    /**
     * @param string $sku
     * @param array|null $rules
     * @param float|int|null $salePrice
     * @param float|int|null $promotionalPrice
     * @param float|int|null $price
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     * @dataProvider variantCatalogPricesDataProvider
     * @since 5.1.0
     */
    public function testVariantCatalogPricesQuerying(string $sku, ?array $rules, float|int|null $salePrice, float|int|null $promotionalPrice, float|int|null $price): void
    {
        $catalogPricingRules = $this->_createCatalogPricingRules($rules);

        $variant = Variant::find()->price($price)->one();
        self::assertInstanceof(Variant::class, $variant);
        self::assertEquals($sku, $variant->getSku());
        self::assertEquals($price, $variant->getPrice());

        if ($promotionalPrice !== null) {
            $variant = Variant::find()->promotionalPrice($promotionalPrice)->one();
            self::assertInstanceof(Variant::class, $variant);
            self::assertEquals($sku, $variant->getSku());
            self::assertEquals($promotionalPrice, $variant->getPromotionalPrice());
        }

        $variant = Variant::find()->salePrice($salePrice)->one();
        self::assertInstanceof(Variant::class, $variant);
        self::assertEquals($sku, $variant->getSku());
        self::assertEquals($salePrice, $variant->getSalePrice());

        // Tidy up at the end of the test
        $this->_deleteCatalogPricingRules($catalogPricingRules);
    }

    /**
     * @param string $sku
     * @param array|null $rules
     * @param float|int|null $salePrice
     * @param float|int|null $promotionalPrice
     * @param float|int|null $price
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     * @dataProvider variantCatalogPricesDataProvider
     * @since 5.2.0
     */
    public function testVariantHasPromotionalPrice(string $sku, ?array $rules, float|int|null $salePrice, float|int|null $promotionalPrice, float|int|null $price): void
    {
        $catalogPricingRules = $this->_createCatalogPricingRules($rules);

        $variantHasPromotionalPrice = Variant::find()->hasPromotionalPrice()->one();
        $variantHasntPromotionalPrice = Variant::find()->hasPromotionalPrice(false)->one();

        if ($promotionalPrice !== null) {
            self::assertInstanceof(Variant::class, $variantHasPromotionalPrice);
            self::assertEquals($sku, $variantHasPromotionalPrice->getSku());
            self::assertEquals($promotionalPrice, $variantHasPromotionalPrice->getPromotionalPrice());
        } else {
            self::assertNull($variantHasPromotionalPrice);
        }

        $this->_deleteCatalogPricingRules($catalogPricingRules);
    }

    /**
     * @param array|null $rules
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    private function _createCatalogPricingRules(?array $rules): array
    {
        $catalogPricingRules = [];

        if (!empty($rules)) {
            foreach ($rules as $rule) {
                $catalogPricingRule = Craft::createObject($rule);
                Plugin::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule);
                $catalogPricingRules[] = $catalogPricingRule->id;
            }

            Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
        }

        return $catalogPricingRules;
    }

    /**
     * @param array|null $rules
     * @return void
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     */
    private function _deleteCatalogPricingRules(?array $rules): void
    {
        // Tidy up at the end of the test
        if (!empty($rules)) {
            foreach ($rules as $catalogPricingRule) {
                Plugin::getInstance()->getCatalogPricingRules()->deleteCatalogPricingRuleById($catalogPricingRule);
            }

            Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
        }
    }

    /**
     * @return array[]
     * @throws InvalidConfigException
     */
    public function variantCatalogPricesDataProvider(): array
    {
        return [
            'no catalog prices' => [
                'sku' => 'rad-hood',
                'rules' => null,
                'salePrice' => 123.99,
                'promotionalPrice' => null,
                'price' => 123.99,
            ],
            'rad hood reduced price' => [
                'sku' => 'rad-hood',
                'rules' => [
                    [
                        'class' => CatalogPricingRule::class,
                        'attributes' => [
                            'name' => 'Test Rule',
                            'storeId' => 1,
                            'applyAmount' => -0.1,
                            'variantCondition' => Craft::$app->getConditions()->createCondition([
                                'class' => CatalogPricingRuleVariantCondition::class,
                                'conditionRules' => [
                                    Craft::$app->getConditions()->createConditionRule([
                                        'type' => SkuConditionRule::class,
                                        'value' => 'rad-hood',
                                    ]),
                                ],
                            ]),
                        ],
                    ],
                ],
                'salePrice' => 111.59,
                'promotionalPrice' => null,
                'price' => 111.59,
            ],
            'rad hood promotional price' => [
                'sku' => 'rad-hood',
                'rules' => [
                    [
                        'class' => CatalogPricingRule::class,
                        'attributes' => [
                            'name' => 'Test Rule',
                            'storeId' => 1,
                            'applyAmount' => -0.1,
                            'isPromotionalPrice' => true,
                            'variantCondition' => Craft::$app->getConditions()->createCondition([
                                'class' => CatalogPricingRuleVariantCondition::class,
                                'conditionRules' => [
                                    Craft::$app->getConditions()->createConditionRule([
                                        'type' => SkuConditionRule::class,
                                        'value' => 'rad-hood',
                                    ]),
                                ],
                            ]),
                        ],
                    ],
                ],
                'salePrice' => 111.59,
                'promotionalPrice' => 111.59,
                'price' => 123.99,
            ],
            'rad hood two rules' => [
                'sku' => 'rad-hood',
                'rules' => [
                    [
                        'class' => CatalogPricingRule::class,
                        'attributes' => [
                            'name' => 'Test Rule - 5%',
                            'storeId' => 1,
                            'applyAmount' => -0.05,
                            'variantCondition' => Craft::$app->getConditions()->createCondition([
                                'class' => CatalogPricingRuleVariantCondition::class,
                                'conditionRules' => [
                                    Craft::$app->getConditions()->createConditionRule([
                                        'type' => SkuConditionRule::class,
                                        'value' => 'rad-hood',
                                    ]),
                                ],
                            ]),
                        ],
                    ],
                    [
                        'class' => CatalogPricingRule::class,
                        'attributes' => [
                            'name' => 'Test Rule - 1%',
                            'storeId' => 1,
                            'applyAmount' => -0.01,
                            'isPromotionalPrice' => true,
                            'variantCondition' => Craft::$app->getConditions()->createCondition([
                                'class' => CatalogPricingRuleVariantCondition::class,
                                'conditionRules' => [
                                    Craft::$app->getConditions()->createConditionRule([
                                        'type' => SkuConditionRule::class,
                                        'value' => 'rad-hood',
                                    ]),
                                ],
                            ]),
                        ],
                    ],
                ],
                'salePrice' => 117.79,
                'promotionalPrice' => null,
                'price' => 117.79,
            ],
        ];
    }
}
