<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

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

        $gateways = (new Query())
            ->select(['id'])
            ->from(Table::GATEWAYS)
            ->all();

        foreach ($gateways as $gateway) {
            $orderCondition = [
                'class' => 'craft\\commerce\\elements\\conditions\\orders\\GatewayOrderCondition',
                'conditionRules' => [],
            ];

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
