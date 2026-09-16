<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product\conditions;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\conditions\products\ProductVariantPriceConditionRule;
use craft\commerce\elements\Product;
use craft\errors\ElementNotFoundException;
use craftcommercetests\fixtures\ProductFixture;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * ProductVariantPriceConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductVariantPriceConditionRuleTest extends Unit
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
     */
    public function testMatchElement(float|int $price, ?string $operator, bool $expected): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantPriceConditionRule $rule */
        $rule = Craft::$app->getConditions()->createConditionRule(ProductVariantPriceConditionRule::class);
        $rule->value = $price;

        if ($operator) {
            $rule->operator = $operator;
        }

        $condition->addConditionRule($rule);

        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $product */
        $product = $productsFixture->getElement('rad-hoodie');

        self::assertSame($expected, $condition->matchElement($product));
    }

    /**
     * @return void
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @dataProvider modifyQueryDataProvider
     */
    public function testModifyQueryMatch(float|int $price, ?string $operator, int $expected): void
    {
        $condition = Product::createCondition();
        /** @var ProductVariantPriceConditionRule $rule */
        $rule = Craft::$app->getConditions()->createConditionRule(ProductVariantPriceConditionRule::class);
        $rule->value = $price;

        if ($operator) {
            $rule->operator = $operator;
        }

        $condition->addConditionRule($rule);

        $query = Product::find();
        $condition->modifyQuery($query);

        self::assertCount($expected, $query->ids());
    }

    /**
     * @return array[]
     */
    public function matchElementDataProvider(): array
    {
        return [
            [100, '>', true],
            [1000, '>', false],
            [1000, '<', true],
            [123.99, null, true],
        ];
    }

    /**
     * @return array[]
     */
    public function modifyQueryDataProvider(): array
    {
        return [
            [100, '>', 1],
            [1000, '>', 0],
            [1000, '<', 2],
            [123.99, null, 1],
        ];
    }
}
