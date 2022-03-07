<?php

namespace craft\commerce\elements\conditions\addresses;

use craft\elements\conditions\addresses\AddressCondition as ElementAddressCondition;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

/**
 * Addresses condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class DiscountAddressCondition extends ElementAddressCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(),
            [
                //PostCodeFormulaConditionRule::class
            ]);
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws NotSupportedException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount Address Condition does not support element queries.');
    }
}