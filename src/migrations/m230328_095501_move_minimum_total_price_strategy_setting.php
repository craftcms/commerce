<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230328_095501_move_minimum_total_price_strategy_setting migration.
 */
class m230328_095501_move_minimum_total_price_strategy_setting extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::STORES, 'minimumTotalPriceStrategy', $this->string()->defaultValue('default'));

        $commerceConfig = Craft::$app->getConfig()->getConfigFromFile('commerce');

        if (empty($commerceConfig)) {
            return true;
        }

        $data = [
            'minimumTotalPriceStrategy' => $commerceConfig['minimumTotalPriceStrategy'] ?? 'default',
        ];
        $this->update(Table::STORES, $data);

        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '5.0.36', '<')) {
            $stores = $projectConfig->get('commerce.stores') ?? [];
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            foreach ($stores as $uid => $store) {
                $projectConfig->set("commerce.stores.$uid", array_merge($store, $data));
            }

            $projectConfig->muteEvents = $muteEvents;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230328_095501_move_minimum_total_price_strategy_setting cannot be reverted.\n";
        return false;
    }
}
