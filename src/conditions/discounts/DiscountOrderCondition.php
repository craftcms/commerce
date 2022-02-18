<?php

namespace craft\commerce\conditions\discounts;

use craft\commerce\conditions\discounts\rules\OrderValuesAttributeConditionRule;
use craft\commerce\elements\Order;
use craft\base\conditions\BaseCondition;

class DiscountOrderCondition extends BaseCondition implements DiscountOrderConditionInterface
{
    /**
     * @inheritDoc
     */
    protected function conditionRuleTypes(): array
    {
        return [
            OrderValuesAttributeConditionRule::class
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