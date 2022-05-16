<?php

namespace  craft\commerce\elements\conditions\orders;

/**
 * Total Price Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
class TotalPriceConditionRule extends OrderValuesAttributeConditionRule
{
    public string $orderAttribute = 'totalPrice';
}
