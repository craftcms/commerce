<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
            CustomerConditionRule::class,
            IsCompletedConditionRule::class,
            ItemTotalConditionRule::class,
            OrderStatusConditionRule::class,
            ReferenceConditionRule::class,
            TotalPriceConditionRule::class,
            TotalQtyConditionRule::class,
        ]);
    }
}
