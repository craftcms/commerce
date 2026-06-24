<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\purchasables;

use craft\elements\conditions\ElementCondition;
use craft\elements\conditions\SiteConditionRule;

/**
 * Catalog Pricing Rule Purchasable condition builder.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRulePurchasableCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        $types = array_filter(parent::selectableConditionRules(), static fn($type) => !in_array($type, [
            SiteConditionRule::class,
        ], true));

        $types[] = PurchasableConditionRule::class;
        $types[] = SkuConditionRule::class;
        $types[] = PurchasableTypeConditionRule::class;
        $types[] = CatalogPricingRulePurchasableCategoryConditionRule::class;

        return $types;
    }
}
