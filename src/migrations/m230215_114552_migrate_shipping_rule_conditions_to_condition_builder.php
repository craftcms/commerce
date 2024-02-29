<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\elements\conditions\orders\DiscountedItemSubtotalConditionRule;
use craft\commerce\elements\conditions\orders\ItemSubtotalConditionRule;
use craft\commerce\elements\conditions\orders\OrderCurrencyValuesAttributeConditionRule;
use craft\commerce\elements\conditions\orders\OrderValuesAttributeConditionRule;
use craft\commerce\elements\conditions\orders\ShippingAddressZoneConditionRule;
use craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition;
use craft\commerce\elements\conditions\orders\TotalQtyConditionRule;
use craft\commerce\elements\conditions\orders\TotalWeightConditionRule;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;

/**
 * m230215_114552_migrate_shipping_rule_conditions_to_condition_builder migration.
 */
class m230215_114552_migrate_shipping_rule_conditions_to_condition_builder extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $shippingRules = (new Query())
            ->select([
                'id',
                'minQty',
                'maxQty',
                'minTotal',
                'maxTotal',
                'minMaxTotalType',
                'minWeight',
                'maxWeight',
                'shippingZoneId',
            ])
            ->from(Table::SHIPPINGRULES)
            ->all();

        if (empty($shippingRules)) {
            return true;
        }

        foreach ($shippingRules as $shippingRule) {
            $orderCondition = new ShippingRuleOrderCondition();

            // Convert min/max qty to order condition rule
            if ($shippingRule['minQty'] > 0 || $shippingRule['maxQty'] > 0) {
                $orderCondition = $this->_setConditionRule(new TotalQtyConditionRule(), $orderCondition, $shippingRule['minQty'], $shippingRule['maxQty'], true);
            }

            // Convert min/max item subtotal to condition rule
            if ($shippingRule['minMaxTotalType'] === 'salePrice' && ($shippingRule['minTotal'] > 0 || $shippingRule['maxTotal'] > 0)) {
                $orderCondition = $this->_setConditionRule(new ItemSubtotalConditionRule(), $orderCondition, $shippingRule['minTotal'], $shippingRule['maxTotal']);
            }

            // Convert min/max item subtotal with discounts to condition rule
            if ($shippingRule['minMaxTotalType'] === 'salePriceWithDiscounts' && ($shippingRule['minTotal'] > 0 || $shippingRule['maxTotal'] > 0)) {
                $orderCondition = $this->_setConditionRule(new DiscountedItemSubtotalConditionRule(), $orderCondition, $shippingRule['minTotal'], $shippingRule['maxTotal']);
            }

            // Convert min/max total weight to condition rule
            if ($shippingRule['minWeight'] > 0 || $shippingRule['maxWeight'] > 0) {
                $orderCondition = $this->_setConditionRule(new TotalWeightConditionRule(), $orderCondition, $shippingRule['minWeight'], $shippingRule['maxWeight']);
            }

            // Convert shipping zone to condition rule
            if ($shippingRule['shippingZoneId']) {
                $rule = new ShippingAddressZoneConditionRule();
                $rule->values = [$shippingRule['shippingZoneId']];

                $orderCondition->addConditionRule($rule);
            }

            // Update shipping rule
            if (!empty($orderCondition->getConditionRules())) {
                $this->update(Table::SHIPPINGRULES, [
                    'orderCondition' => Db::prepareValueForDb($orderCondition->getConfig()),
                ], [
                    'id' => $shippingRule['id'],
                ]);
            }
        }

        return true;
    }

    /**
     * @param OrderValuesAttributeConditionRule|OrderCurrencyValuesAttributeConditionRule $rule
     * @param ShippingRuleOrderCondition $orderCondition
     * @param mixed $min
     * @param mixed $max
     * @param bool $adjustValues
     * @return ShippingRuleOrderCondition
     */
    private function _setConditionRule(OrderValuesAttributeConditionRule|OrderCurrencyValuesAttributeConditionRule $rule, ShippingRuleOrderCondition $orderCondition, mixed $min, mixed $max, bool $adjustValues = false): ShippingRuleOrderCondition
    {
        // Write this manually because at the moment the operator constants are all protected and not public
        $rule->operator = 'between';

        if ($max > 0) {
            $rule->maxValue = $adjustValues ? $max - 1 : $max;
        }

        if ($min > 0) {
            $rule->value = $adjustValues ? $min - 1 : $min;
        }

        $orderCondition->addConditionRule($rule);

        return $orderCondition;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230215_114552_migrate_shipping_rule_conditions_to_condition_builder cannot be reverted.\n";
        return false;
    }
}
