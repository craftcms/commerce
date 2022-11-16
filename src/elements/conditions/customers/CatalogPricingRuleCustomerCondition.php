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
    protected function conditionRuleTypes(): array
    {
        $types = array_filter(parent::conditionRuleTypes(), static function($type) {
            return !in_array($type, [
                LastLoginDateConditionRule::class,
                SiteConditionRule::class,
            ], true);
        });

        return $types;
    }
}