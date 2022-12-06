<?php

namespace  craft\commerce\elements\conditions\orders;

use Craft;

/**
 * Item Subtotal Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class ItemSubtotalConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    public string $orderAttribute = 'itemSubtotal';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Item Subtotal');
    }
}
