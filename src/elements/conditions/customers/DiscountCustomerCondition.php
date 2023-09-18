<?php

namespace craft\commerce\elements\conditions\customers;

use craft\commerce\elements\conditions\users\DiscountGroupConditionRule;
use craft\elements\conditions\users\UserCondition as UserElementCondition;

/**
 * Discount Order condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class DiscountCustomerCondition extends UserElementCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            HasOrdersConditionRule::class,
            SignedInConditionRule::class,
            DiscountGroupConditionRule::class,
        ]);
    }
}
