<?php

namespace craft\commerce\elements\conditions\addresses;

use craft\elements\conditions\addresses\AddressCondition as ElementAddressCondition;
use craft\elements\conditions\SiteConditionRule;
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
        $conditionRuleTypes = parent::conditionRuleTypes();
        // Removes Site Condition Rule support for Zone Address Condition.
        if (($key = array_search(SiteConditionRule::class, $conditionRuleTypes)) !== false) {
            unset($conditionRuleTypes[$key]);
        }

        return array_merge($conditionRuleTypes,
            [
                PostalCodeFormulaConditionRule::class,
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
