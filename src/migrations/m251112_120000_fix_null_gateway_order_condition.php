<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\services\Gateways;
use craft\db\Migration;
use craft\db\Query;

/**
 * m251112_120000_fix_null_gateway_order_condition migration.
 */
class m251112_120000_fix_null_gateway_order_condition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = \Craft::$app->getProjectConfig();

        $projectConfig->muteEvents = true;

        // Fix gateways with missing order conditions
        $gateways = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::GATEWAYS)
            ->where(['orderCondition' => null])
            ->all();

        if (!empty($gateways)) {
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

                if ($config && !$gateway['isArchived']) {
                    $config['orderCondition'] = $orderCondition;
                    $projectConfig->set(Gateways::CONFIG_GATEWAY_KEY . '.' . $gateway['uid'], $config);
                }
            }
        }

        $projectConfig->muteEvents = false;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251112_120000_fix_null_gateway_order_condition cannot be reverted.\n";
        return false;
    }
}
