<?php

namespace craft\commerce\elements\conditions\customers;

use craft\elements\conditions\users\UserCondition as UserElementCondition;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

/**
 * Discount Order condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class DiscountCustomerCondition extends UserElementCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        $types = array_merge(parent::conditionRuleTypes(), [
            HasOrdersInDateRange::class,
            HasOrdersInLastPeriod::class
        ]);

        return $types;
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws NotSupportedException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount User Condition does not support element queries.');
    }
}
