<?php

namespace craft\commerce\conditions\discounts\rules;

use craft\commerce\conditions\discounts\BaseOrderAttributeNumberConditionRule;

/**
 * Order Discount Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class OrderTotalPriceConditionRule extends BaseOrderAttributeNumberConditionRule
{
    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return \Craft::t('commerce', 'Total Price');
    }

    /**
     * @inheritdoc
     */
    protected function getOrderAttributeValue($order)
    {
        return $order->getTotalPrice();
    }
}