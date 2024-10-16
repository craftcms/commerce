<?php

namespace craft\commerce\elements\conditions\products;

use craft\commerce\elements\Product;
use craft\elements\conditions\ElementCondition;

/**
 * Product query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class ProductCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    public ?string $elementType = Product::class;

    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            ProductTypeConditionRule::class,
            ProductVariantSearchConditionRule::class,
            ProductVariantSkuConditionRule::class,
            ProductVariantStockConditionRule::class,
            ProductVariantHasUnlimitedStockConditionRule::class,
            ProductVariantPriceConditionRule::class,
        ]);
    }
}
