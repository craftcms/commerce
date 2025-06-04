<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product\conditions;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\products\ProductVariantStockConditionRule;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\elements\VariantCollection;
use craft\commerce\Plugin;
use craft\db\Query;
use craftcommercetests\fixtures\ProductFixture;

/**
 * ProductVariantStockConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductVariantStockConditionRuleTest extends Unit
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
     */
    public function testMatchElement(): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantStockConditionRule::class);
        $rule->value = 10;
        $rule->operator = '<';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        // /** @var Product $product */
        // $product = $productsFixture->getElement('rad-hoodie');


        $product = $this->make(Product::class, [
            'getVariants' => fn() => VariantCollection::make([
                $this->make(Variant::class, [
                    'getStock' => 9,
                    'inventoryTracked' => true,
                ]),
            ]),
        ]);

        self::assertTrue($condition->matchElement($product));
    }

    /**
     * @group Product
     */
    public function testNotMatchElement(): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantStockConditionRule::class);
        $rule->value = 10;
        $rule->operator = '<';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        self::assertFalse($condition->matchElement($product));
    }

    public function testModifyQueryMatch(): void
    {
        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $condition = Product::createCondition();
        /** @var ProductVariantStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantStockConditionRule::class);
        $rule->value = 10;
        $rule->operator = '<';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        $originalValues = (new Query())
            ->from(Table::PURCHASABLES_STORES)
            ->select(['purchasableId', 'stock', 'inventoryTracked'])
            ->indexBy('purchasableId')
            ->all();

        \Craft::$app->getDb()->createCommand()
            ->update(Table::PURCHASABLES_STORES, ['stock' => 9, 'inventoryTracked' => true], ['storeId' => $primaryStore->id])
            ->execute();

        $query = Product::find();
        $condition->modifyQuery($query);

        self::assertContainsEquals($product->id, $query->ids());

        foreach ($originalValues as $purchasableId => $values) {
            \Craft::$app->getDb()->createCommand()
                ->update(Table::PURCHASABLES_STORES, $values, ['purchasableId' => $purchasableId, 'storeId' => $primaryStore->id])
                ->execute();
        }
    }
}
