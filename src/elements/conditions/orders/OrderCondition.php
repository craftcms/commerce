<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\elements\Order;
use craft\elements\conditions\ElementCondition;

/**
 * Order query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class OrderCondition extends ElementCondition
{
    public ?string $elementType = Order::class;
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            DateOrderedConditionRule::class,
            CustomerConditionRule::class,
            CompletedConditionRule::class,
            PaidConditionRule::class,
            HasPurchasableConditionRule::class,
            ItemSubtotalConditionRule::class,
            ItemTotalConditionRule::class,
            OrderStatusConditionRule::class,
            OrderSiteConditionRule::class,
            ReferenceConditionRule::class,
            ShippingMethodConditionRule::class,
            TotalDiscountConditionRule::class,
            TotalPaidConditionRule::class,
            TotalPriceConditionRule::class,
            TotalQtyConditionRule::class,
            TotalTaxConditionRule::class,
            TotalConditionRule::class,
        ]);
    }
}
