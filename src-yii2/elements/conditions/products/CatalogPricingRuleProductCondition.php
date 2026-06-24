<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\products;

use craft\helpers\ArrayHelper;

/**
 * Catalog Pricing Rule Product query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.1.0
 */
class CatalogPricingRuleProductCondition extends ProductCondition
{
    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        $rules = parent::selectableConditionRules();

        return ArrayHelper::withoutValue($rules, ProductVariantHasUnlimitedStockConditionRule::class);
    }
}
