<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\services\Gateways;
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

        $projectConfig = \Craft::$app->getProjectConfig();

        $projectConfig->muteEvents = true;

        $gateways = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::GATEWAYS)
            ->where(['isArchived' => false])
            ->all();

        foreach ($gateways as $gateway) {
            $config = $projectConfig->get(Gateways::CONFIG_GATEWAY_KEY . '.' . $gateway['uid']);

            $orderCondition = [
                'class' => 'craft\\commerce\\elements\\conditions\\orders\\GatewayOrderCondition',
                'conditionRules' => [],
            ];

            $this->update(Table::GATEWAYS,
                ['orderCondition' => json_encode($orderCondition)],
                ['id' => $gateway['id']]
            );

            $config['orderCondition'] = $orderCondition;
            $projectConfig->set(Gateways::CONFIG_GATEWAY_KEY . '.' . $gateway['uid'], $config);
        }

        $projectConfig->muteEvents = false;

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
