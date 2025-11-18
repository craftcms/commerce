<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\customers;

use craft\elements\conditions\SiteConditionRule;
use craft\elements\conditions\users\LastLoginDateConditionRule;
use craft\elements\conditions\users\UserCondition;

/**
 * Catalog Pricing Rule Customer condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRuleCustomerCondition extends UserCondition
{
    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(
            array_filter(parent::selectableConditionRules(), static fn($type) => !in_array($type, [
                // Remove rules that don't make sense in this context
                LastLoginDateConditionRule::class,
                SiteConditionRule::class,
            ], true)
            ),
            // Add additional rules
            [
                CatalogPricingRuleCustomerConditionRule::class,
            ]
        );
    }
}
