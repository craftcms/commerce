<?php

namespace craft\commerce\elements\conditions\users;

use craft\elements\conditions\users\GroupConditionRule as ElementGroupConditionRule;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

/**
 * Discount user group condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
class DiscountGroupConditionRule extends ElementGroupConditionRule
{
    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount user group rule does not support element queries.');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }
}