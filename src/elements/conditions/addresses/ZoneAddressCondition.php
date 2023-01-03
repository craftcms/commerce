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
class ZoneAddressCondition extends ElementAddressCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        $parentConditionRuleTypes = parent::conditionRuleTypes();

        if (($key = array_search('craft\elements\conditions\SiteConditionRule', $parentConditionRuleTypes)) !== false) {
            unset($parentConditionRuleTypes[$key]);
        }

        return array_merge($parentConditionRuleTypes,
            [
                PostalCodeFormulaConditionRule::class,
                CommerceSiteConditionRule::class,
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
