<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\products\ProductTypeConditionRule;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\ProductTypeFixture;

/**
 * ProductTypeConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductTypeConditionRuleTest extends Unit
{
    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'product-types' => [
                'class' => ProductTypeFixture::class,
            ],
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
        $productTypeModel = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('hoodies');
        $condition = Product::createCondition();
        /** @var ProductTypeConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductTypeConditionRule::class);
        $rule->setValues([$productTypeModel->uid]);
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
        $productTypeModel = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $condition = Product::createCondition();
        /** @var ProductTypeConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductTypeConditionRule::class);
        $rule->setValues([$productTypeModel->uid]);
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        self::assertFalse($condition->matchElement($product));
    }

    /**
     * @group Product
     */
    public function testMatchElementNotIn(): void
    {
        $productTypeModel = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts');
        $condition = Product::createCondition();
        /** @var ProductTypeConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(ProductTypeConditionRule::class);
        $rule->setValues([$productTypeModel->uid]);
        $rule->operator = 'ni';
        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        self::assertTrue($condition->matchElement($product));
    }
}
