<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use CraftCms\Commerce\Order\Elements\Order;
use Override;

class OrderCondition extends ElementCondition
{
    #[Override]
    public ?string $elementType = Order::class;

    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            DateOrderedConditionRule::class,
            CompletedConditionRule::class,
            CouponCodeConditionRule::class,
            CustomerConditionRule::class,
            HasAdminNoticesConditionRule::class,
            PaidConditionRule::class,
            HasPurchasableConditionRule::class,
            ContainsPurchasablesConditionRule::class,
            ItemSubtotalConditionRule::class,
            ItemTotalConditionRule::class,
            OrderStatusConditionRule::class,
            OrderSiteConditionRule::class,
            PaymentGatewayConditionRule::class,
            ReferenceConditionRule::class,
            ShippingMethodConditionRule::class,
            TotalDiscountConditionRule::class,
            TotalPaidConditionRule::class,
            TotalPriceConditionRule::class,
            TotalQtyConditionRule::class,
            TotalTaxConditionRule::class,
            TotalConditionRule::class,
            TotalWeightConditionRule::class,
        ]);
    }
}
