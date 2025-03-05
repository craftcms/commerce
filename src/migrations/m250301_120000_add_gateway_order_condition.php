<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\helpers\App;
use craft\helpers\StringHelper;

/**
 * m250301_120000_add_gateway_order_condition migration.
 */
class m250301_120000_add_gateway_order_condition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add the order condition column to the gateways table
        $this->addColumn(Table::GATEWAYS, 'orderCondition', $this->text());
        
        // Update existing gateways to use the enabled condition based on their isFrontendEnabled value
        $gateways = $this->db->createCommand('SELECT id, isFrontendEnabled FROM ' . Table::GATEWAYS)->queryAll();
        
        foreach ($gateways as $gateway) {
            $isFrontendEnabled = $gateway['isFrontendEnabled'];
            $isEnabled = false;
            
            // Try to parse the value as a boolean
            try {
                $isEnabled = App::parseBooleanEnv($isFrontendEnabled);
            } catch (\Throwable $e) {
                // If there's an error parsing (like an invalid env var), default to false
                $isEnabled = false;
            }
            
            // Base order condition class
            $orderCondition = [
                'class' => 'craft\\commerce\\elements\\conditions\\orders\\GatewayOrderCondition',
                'conditionRules' => [],
            ];
            
            // Only add the EnabledGatewayConditionRule if the gateway is enabled
            if ($isEnabled) {
                $orderCondition['conditionRules'][] = [
                    'class' => 'craft\\commerce\\elements\\conditions\\orders\\EnabledGatewayConditionRule',
                    'uid' => StringHelper::UUID(),
                    'value' => true,
                ];
            }
            
            $this->update(Table::GATEWAYS, 
                ['orderCondition' => json_encode($orderCondition)], 
                ['id' => $gateway['id']]
            );
        }
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250301_120000_add_gateway_order_condition cannot be reverted.\n";
        return false;
    }
}