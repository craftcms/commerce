<?php

namespace  craft\commerce\elements\conditions\orders;

use Craft;

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

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Total Price');
    }
}
