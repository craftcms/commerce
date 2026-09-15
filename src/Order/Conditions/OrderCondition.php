<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\Contracts\ConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\ElementCondition;
use CraftCms\Commerce\Order\Elements\Order;
use Override;

class OrderCondition extends ElementCondition
{
    #[Override]
    public ?string $elementType = Order::class;

    /**
     * @var string[] Query params that selectable rules shouldn't compete with, e.g. because
     * they're already accounted for outside the condition builder (see `HasOrdersConditionRule`).
     */
    public array $queryParams = [];

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

    #[Override]
    protected function isConditionRuleSelectable(ConditionRuleInterface $rule): bool
    {
        if (!parent::isConditionRuleSelectable($rule)) {
            return false;
        }

        // Exclude rules whose exclusive query param is already reserved by `$queryParams`
        if (method_exists($rule, 'getExclusiveQueryParams')) {
            foreach ($rule->getExclusiveQueryParams() as $param) {
                if (in_array($param, $this->queryParams, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
