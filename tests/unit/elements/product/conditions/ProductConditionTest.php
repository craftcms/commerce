<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\product\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\products\ProductCondition;
use craft\commerce\elements\conditions\products\ProductTypeConditionRule;
use craft\commerce\elements\conditions\products\ProductVariantSkuConditionRule;
use craft\commerce\elements\conditions\products\ProductVariantStockConditionRule;
use craft\commerce\elements\Product;
use craftcommercetests\fixtures\ProductFixture;

/**
 * ProductConditionTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductTest extends Unit
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
     * @group Product Condition
     */
    public function testCreateCondition(): void
    {
        self::assertInstanceOf(ProductCondition::class, Product::createCondition());
    }

    /**
     * @group Product Condition
     */
    public function testConditionRuleTypes(): void
    {
        $rules = array_keys(Product::createCondition()->getSelectableConditionRules());

        self::assertContains(ProductTypeConditionRule::class, $rules);
        self::assertContains(ProductVariantSkuConditionRule::class, $rules);
        self::assertContains(ProductVariantStockConditionRule::class, $rules);
    }
}
