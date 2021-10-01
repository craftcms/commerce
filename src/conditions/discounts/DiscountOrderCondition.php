<?php

namespace craft\commerce\conditions\discounts;

use craft\commerce\conditions\discounts\rules\OrderItemSubtotalConditionRule;
use craft\commerce\conditions\discounts\rules\OrderItemTotalConditionRule;
use craft\commerce\conditions\discounts\rules\OrderTotalConditionRule;
use craft\commerce\conditions\discounts\rules\OrderTotalPriceConditionRule;
use craft\commerce\conditions\discounts\rules\OrderTotalQtyConditionRule;
use craft\commerce\conditions\discounts\rules\TotalQtyConditionRule;
use craft\commerce\elements\Order;
use craft\conditions\BaseCondition;

class DiscountOrderCondition extends BaseCondition implements DiscountOrderConditionInterface
{
    /**
     * @inheritDoc
     */
    protected function conditionRuleTypes(): array
    {
        return [
            OrderTotalQtyConditionRule::class,
            OrderItemTotalConditionRule::class,
            OrderItemSubtotalConditionRule::class,
            OrderTotalPriceConditionRule::class
        ];
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function matchOrder(Order $order): bool
    {
        /** @var DiscountOrderConditionRuleInterface $rule */
        foreach ($this->getConditionRules() as $rule) {
            if (!$rule->matchOrder($order)) {
                return false;
            }
        }

        return true;
    }
}