<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\services\Gateways;
use craft\db\Migration;
use craft\db\Query;

/**
 * m250815_120000_add_gateway_address_conditions migration.
 */
class m250815_120000_add_gateway_address_conditions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add the address condition columns to the gateways table
        $this->addColumn(Table::GATEWAYS, 'billingAddressCondition', $this->text());
        $this->addColumn(Table::GATEWAYS, 'shippingAddressCondition', $this->text());

        $projectConfig = \Craft::$app->getProjectConfig();

        $projectConfig->muteEvents = true;

        $gateways = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::GATEWAYS)
            ->all();

        foreach ($gateways as $gateway) {
            $config = $projectConfig->get(Gateways::CONFIG_GATEWAY_KEY . '.' . $gateway['uid']);

            $billingAddressCondition = [
                'class' => 'craft\\commerce\\elements\\conditions\\addresses\\GatewayAddressCondition',
                'conditionRules' => [],
            ];

            $shippingAddressCondition = [
                'class' => 'craft\\commerce\\elements\\conditions\\addresses\\GatewayAddressCondition',
                'conditionRules' => [],
            ];

            $this->update(Table::GATEWAYS,
                [
                    'billingAddressCondition' => json_encode($billingAddressCondition),
                    'shippingAddressCondition' => json_encode($shippingAddressCondition),
                ],
                ['id' => $gateway['id']]
            );

            $config['billingAddressCondition'] = $billingAddressCondition;
            $config['shippingAddressCondition'] = $shippingAddressCondition;
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
        echo "m250815_120000_add_gateway_address_conditions cannot be reverted.\n";
        return false;
    }
}
