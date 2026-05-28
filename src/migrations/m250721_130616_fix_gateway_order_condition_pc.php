<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\services\Gateways;
use craft\db\Migration;
use craft\db\Query;

/**
 * m250721_130616_fix_gateway_order_condition_pc migration.
 */
class m250721_130616_fix_gateway_order_condition_pc extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // This is almost a duplicate of `m250301_120000_add_gateway_order_condition` to fix for those already migrated
        $projectConfig = \Craft::$app->getProjectConfig();

        $projectConfig->muteEvents = true;

        // Fix gateways with missing order conditions
        $gateways = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::GATEWAYS)
            ->where(['orderCondition' => null])
            ->andWhere(['isArchived' => false])
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
        echo "m250721_130616_fix_gateway_order_condition_pc cannot be reverted.\n";
        return false;
    }
}
