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
use craft\commerce\elements\Product;
use craftcommercetests\fixtures\ProductFixture;

/**
 * ProductVariantSkuConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductVariantSkuConditionRuleTest extends Unit
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
        /** @var ProductVariantSkuConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantSkuConditionRule::class);
        $rule->value = 'rad-hood';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        self::assertTrue($condition->matchElement($product));
    }

    /**
     * @group Product
     */
    public function testNotMatchElement(): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantSkuConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantSkuConditionRule::class);
        $rule->value = 'does-not-exist';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        self::assertFalse($condition->matchElement($product));
    }

    public function testModifyQueryMatch(): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantSkuConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductVariantSkuConditionRule::class);
        $rule->value = 'rad';
        $rule->operator = 'bw';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        $query = Product::find();
        $condition->modifyQuery($query);

        self::assertContainsEquals($product->id, $query->ids());
    }
}
