<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\conditions\purchasables\SkuConditionRule;
use craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\ProductFixture;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * PricingCatalogTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.x.x
 */
class ProductPricingCatalogTest extends Unit
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
     * @param string $sku
     * @param array|null $rules
     * @param float|int|null $price
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     * @dataProvider productCatalogPricesDataProvider
     */
    public function testProductCatalogPrices(string $sku, ?array $rules, float|int|null $price): void
    {
        $catalogPricingRules = $this->_createCatalogPricingRules($rules);

        $product = Product::find()->defaultSku($sku)->one();

        self::assertInstanceof(Product::class, $product);
        self::assertEquals($price, $product->getDefaultPrice());

        // Tidy up at the end of the test
        $this->_deleteCatalogPricingRules($catalogPricingRules);
    }

    /**
     * @param string $sku
     * @param array|null $rules
     * @param float|int|null $price
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     * @dataProvider productCatalogPricesDataProvider
     */
    public function testProductCatalogPricesQuerying(string $sku, ?array $rules, float|int|null $price): void
    {
        $catalogPricingRules = $this->_createCatalogPricingRules($rules);

        $product = Product::find()->defaultPrice($price)->one();
        $variant = $product->getDefaultVariant();
        self::assertInstanceof(Product::class, $product);
        self::assertInstanceof(Variant::class, $variant);
        self::assertEquals($sku, $product->defaultSku);
        self::assertEquals($sku, $variant->getSku());
        self::assertEquals($price, $product->defaultPrice);
        self::assertEquals($price, $variant->getPrice());

        // Tidy up at the end of the test
        $this->_deleteCatalogPricingRules($catalogPricingRules);
    }

    /**
     * @param string $sku
     * @param array|null $rules
     * @param float|int|null $price
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     * @throws \yii\db\Exception
     * @dataProvider productCatalogPricesDataProvider
     */
    public function testProductCatalogPricesSorting(string $sku, ?array $rules, float|int|null $price): void
    {
        $catalogPricingRules = $this->_createCatalogPricingRules($rules);

        $orderBy = ['defaultPrice' => SORT_ASC];
        $products = Product::find()->orderBy($orderBy)->all();
        $price = null;
        foreach ($products as $product) {
            self::assertGreaterThanOrEqual($price, $product->getDefaultPrice());
            $price = $product->getDefaultPrice();
        }

        $orderBy = ['defaultPrice' => SORT_DESC];
        $products = Product::find()->orderBy($orderBy)->all();
        $price = 999999999;
        foreach ($products as $product) {
            self::assertLessThanOrEqual($price, $product->getDefaultPrice());
            $price = $product->getDefaultPrice();
        }

        // Tidy up at the end of the test
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
    public function productCatalogPricesDataProvider(): array
    {
        return [
            'no catalog prices' => [
                'sku' => 'rad-hood',
                'rules' => null,
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
                'price' => 117.79,
            ],
        ];
    }
}
