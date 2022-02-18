<?php

namespace craft\commerce\conditions\discounts\rules;

use Craft;
use craft\base\conditions\BaseNumberConditionRule;
use craft\commerce\conditions\discounts\DiscountOrderConditionRuleInterface;
use craft\commerce\elements\Order;

/**
 * Order Number Attribute Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
class OrderValuesAttributeConditionRule extends BaseNumberConditionRule implements DiscountOrderConditionRuleInterface
{
    public string $orderAttribute = 'totalPrice';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        Craft::t('commerce', 'Order Value');
    }

    /**
     * @inheritdoc
     */
    public function matchOrder(Order $order): bool
    {
        $field = $this->orderAttribute;
        switch ($this->operator) {
            case '=':
                return $order->$field == $this->value;
            case '!=':
                return $order->$field != $this->value;
            case '<':
                return $order->$field < $this->value;
            case '<=':
                return $order->$field <= $this->value;
            case '>':
                return $order->$field > $this->value;
            case '>=':
                return $order->$field >= $this->value;
        }

        return false;
    }
}