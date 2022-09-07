<?php

namespace craft\commerce\elements\conditions\orders;

use craft\elements\conditions\ElementCondition;

/**
 * Order query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class OrderCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            TotalPriceConditionRule::class,
            ShippingMethodConditionRule::class,
        ]);
    }
}
