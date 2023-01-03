<?php

namespace craft\commerce\migrations;

use Craft;
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

        // iterate through all, json decode the 'condition'
        foreach ($taxZoneRecords as $i => $taxZoneRecord) {
            $condition = json_decode($taxZoneRecord->attributes['condition'], true);

            // if it contains "conditionRules":[{"class":"craft\\elements\\conditions\\SiteConditionRule"
            // change it to "conditionRules":[{"class":"craft\\commerce\\elements\\conditions\\CommerceSiteConditionRule"
            if ($condition['class'] === 'craft\commerce\elements\conditions\addresses\ZoneAddressCondition' &&
                !empty($condition['conditionRules'])
            ) {
                foreach ($condition['conditionRules'] as $j => $rule) {
                    if ($rule['class'] === 'craft\elements\conditions\SiteConditionRule') {
                        $condition['conditionRules'][$j]['class'] = 'craft\commerce\elements\conditions\CommerceSiteConditionRule';
                        $taxZoneRecord->setAttribute('condition', json_encode($condition));
                        $taxZoneRecord->save();
                    }
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // get all tax zone records
        $taxZoneRecords = TaxZone::find()->all();

        // iterate through all, json decode the 'condition'
        foreach ($taxZoneRecords as $i => $taxZoneRecord) {
            $condition = json_decode($taxZoneRecord->attributes['condition'], true);

            // if it contains "conditionRules":[{"class":"craft\\commerce\\elements\\conditions\\CommerceSiteConditionRule"
            // change it back to "conditionRules":[{"class":"craft\\elements\\conditions\\SiteConditionRule"
            if ($condition['class'] === 'craft\commerce\elements\conditions\addresses\ZoneAddressCondition' &&
                !empty($condition['conditionRules'])
            ) {
                foreach ($condition['conditionRules'] as $j => $rule) {
                    if ($rule['class'] === 'craft\commerce\elements\conditions\CommerceSiteConditionRule') {
                        $condition['conditionRules'][$j]['class'] = 'craft\elements\conditions\SiteConditionRule';
                        $taxZoneRecord->setAttribute('condition', json_encode($condition));
                        $taxZoneRecord->save();
                    }
                }
            }
        }

        return true;
    }
}
