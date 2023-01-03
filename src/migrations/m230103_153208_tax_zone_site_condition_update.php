<?php

namespace craft\commerce\migrations;

use craft\commerce\records\ShippingZone;
use craft\commerce\records\TaxZone;
use craft\db\Migration;

/**
 * m230103_153208_tax_zone_site_condition_update migration.
 */
class m230103_153208_tax_zone_site_condition_update extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // get all tax zone records
        $taxZoneRecords = TaxZone::find()->all();

        // update conditionRule class for site condition
        $this->_updateSiteConditionRule(
            $taxZoneRecords,
            'craft\elements\conditions\SiteConditionRule',
            'craft\commerce\elements\conditions\addresses\CommerceSiteConditionRule',
        );

        // get all shipping zone records
        $shippingZoneRecords = ShippingZone::find()->all();

        // update conditionRule class for site condition
        $this->_updateSiteConditionRule(
            $shippingZoneRecords,
            'craft\elements\conditions\SiteConditionRule',
            'craft\commerce\elements\conditions\addresses\CommerceSiteConditionRule',
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // get all tax zone records
        $taxZoneRecords = TaxZone::find()->all();

        // update conditionRule class for site condition
        $this->_updateSiteConditionRule(
            $taxZoneRecords,
            'craft\commerce\elements\conditions\addresses\CommerceSiteConditionRule',
            'craft\elements\conditions\SiteConditionRule',
        );

        // get all shipping zone records
        $shippingZoneRecords = ShippingZone::find()->all();

        // update conditionRule class for site condition
        $this->_updateSiteConditionRule(
            $shippingZoneRecords,
            'craft\commerce\elements\conditions\addresses\CommerceSiteConditionRule',
            'craft\elements\conditions\SiteConditionRule',
        );

        return true;
    }

    private function _updateSiteConditionRule(array $records, string $changeFrom, string $changeTo): void
    {
        // iterate through all, json decode the 'condition'
        foreach ($records as $i => $record) {
            $condition = json_decode($record->attributes['condition'], true);

            // if it contains "conditionRules":[{"class":$changeFrom
            // change it to "conditionRules":[{"class":$changeTo
            if ($condition['class'] === 'craft\commerce\elements\conditions\addresses\ZoneAddressCondition' &&
                !empty($condition['conditionRules'])
            ) {
                foreach ($condition['conditionRules'] as $j => $rule) {
                    if ($rule['class'] === $changeFrom) {
                        $condition['conditionRules'][$j]['class'] = $changeTo;
                        $record->setAttribute('condition', json_encode($condition));
                        $record->save();
                    }
                }
            }
        }
    }
}
