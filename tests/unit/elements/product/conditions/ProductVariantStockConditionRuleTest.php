<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\products\ProductTypeConditionRule;
use craft\commerce\elements\conditions\products\ProductVariantSkuConditionRule;
use craft\commerce\elements\conditions\products\ProductVariantStockConditionRule;
use craft\commerce\elements\Product;
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
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        $variants = $product->getVariants();
        $variants[0]->stock = 9;
        $variants[0]->hasUnlimitedStock = false;
        $product->setVariants($variants);

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
        $condition = Product::createCondition();
        /** @var ProductVariantStockConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantStockConditionRule::class);
        $rule->value = 10;
        $rule->operator = '<';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        $variants = $product->getVariants();
        $variants[0]->stock = 9;
        $variants[0]->hasUnlimitedStock = false;
        $product->setVariants($variants);

        \Craft::$app->getElements()->saveElement($product, false, false, false, false);

        $query = Product::find();
        $condition->modifyQuery($query);

        self::assertContainsEquals($product->id, $query->ids());
    }
}
