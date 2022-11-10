<?php

namespace  craft\commerce\elements\conditions\orders;

use Craft;

/**
 * Item Total Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class ItemTotalConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    public string $orderAttribute = 'itemTotal';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Item Total');
    }
}
