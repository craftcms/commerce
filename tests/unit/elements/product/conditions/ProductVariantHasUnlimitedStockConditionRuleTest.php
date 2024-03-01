<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product\conditions;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\products\ProductVariantHasUnlimitedStockConditionRule;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craftcommercetests\fixtures\ProductFixture;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * ProductVariantHasUnlimitedStockConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductVariantHasUnlimitedStockConditionRuleTest extends Unit
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
     * @group Product
     * @dataProvider matchElementDataProvider
     * @param bool $hasUnlimitedStock
     * @throws InvalidConfigException
     */
    public function testMatchElement(bool $hasUnlimitedStock): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantHasUnlimitedStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantHasUnlimitedStockConditionRule::class);
        $rule->value = $hasUnlimitedStock;
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        if (!$hasUnlimitedStock) {
            $variants = $product->getVariants();
            $variants->each(function(&$variant) {
                $variant->hasUnlimitedStock = false;
            });
            $product->setVariants($variants);
        }

        self::assertTrue($condition->matchElement($product));
    }

    /**
     * @group Product
     * @dataProvider matchElementDataProvider
     * @param bool $hasUnlimitedStock
     * @throws InvalidConfigException
     */
    public function testNotMatchElement(bool $hasUnlimitedStock): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantHasUnlimitedStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantHasUnlimitedStockConditionRule::class);
        $rule->value = $hasUnlimitedStock;
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        if ($hasUnlimitedStock) {
            $variants = $product->getVariants();
            $variants->each(function(&$variant) {
                $variant->hasUnlimitedStock = false;
            });
            $product->setVariants($variants);
        }

        self::assertFalse($condition->matchElement($product));
    }

    /**
     * @param bool $hasUnlimitedStock
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws \Throwable
     * @dataProvider matchElementDataProvider
     */
    public function testModifyQueryMatch(bool $hasUnlimitedStock): void
    {
        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $condition = Product::createCondition();
        /** @var ProductVariantHasUnlimitedStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantHasUnlimitedStockConditionRule::class);
        $rule->value = $hasUnlimitedStock;
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        if (!$hasUnlimitedStock) {
            $originalValues = (new Query())
                ->from(Table::PURCHASABLES_STORES)
                ->select(['purchasableId', 'stock', 'inventoryTracked'])
                ->indexBy('purchasableId')
                ->all();

            \Craft::$app->getDb()->createCommand()
                ->update(Table::PURCHASABLES_STORES, ['stock' => 9, 'inventoryTracked' => true], ['storeId' => $primaryStore->id])
                ->execute();
        }

        $query = Product::find();
        $condition->modifyQuery($query);

        self::assertContainsEquals($product->id, $query->ids());

        if (!$hasUnlimitedStock) {
            foreach ($originalValues as $purchasableId => $values) {
                \Craft::$app->getDb()->createCommand()
                    ->update(Table::PURCHASABLES_STORES, $values, ['purchasableId' => $purchasableId, 'storeId' => $primaryStore->id])
                    ->execute();
            }
        }
    }

    /**
     * @return array
     */
    public function matchElementDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }
}
