<?php

namespace craft\commerce\conditions\discounts;

use craft\commerce\conditions\discounts\DiscountOrderConditionRuleInterface;
use craft\commerce\elements\Order;
use craft\conditions\BaseNumberOperatorConditionRule;

/**
 * Base Order Attribute Number Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
abstract class BaseOrderAttributeNumberConditionRule extends BaseNumberOperatorConditionRule implements DiscountOrderConditionRuleInterface
{
    /**
     * The attribute value retrieved from the order
     *
     * @param Order $order
     * @return int|float
     */
    abstract protected function getOrderAttributeValue(Order $order);

    /**
     * @inheritdoc
     */
    public function matchOrder(Order $order): bool
    {
        switch ($this->operator) {
            case '=':
                return $this->getOrderAttributeValue($order) == $this->value;
            case '!=':
                return $this->getOrderAttributeValue($order) != $this->value;
            case '<':
                return $this->getOrderAttributeValue($order) < $this->value;
            case '<=':
                return $this->getOrderAttributeValue($order) <= $this->value;
            case '>':
                return $this->getOrderAttributeValue($order) > $this->value;
            case '>=':
                return $this->getOrderAttributeValue($order) >= $this->value;
        }

        return false;
    }
}