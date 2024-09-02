<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\products;

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
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            ProductTypeConditionRule::class,
            ProductVariantSkuConditionRule::class,
            ProductVariantStockConditionRule::class,
            ProductVariantHasUnlimitedStockConditionRule::class,
            ProductVariantPriceConditionRule::class,
        ]);
    }
}
