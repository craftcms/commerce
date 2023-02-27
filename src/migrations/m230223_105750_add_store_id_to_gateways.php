<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230223_105750_add_store_id_to_gateways migration.
 */
class m230223_105750_add_store_id_to_gateways extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::GATEWAYS, 'storeId', $this->integer());

        $primaryStore = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->one();

        $this->addForeignKey(null, Table::GATEWAYS, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::GATEWAYS, ['storeId'], false);

        $this->update(Table::GATEWAYS, ['storeId' => $primaryStore['id']]);

        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '5.0.25', '<')) {
            $gateways = $projectConfig->get('commerce.gateways') ?? [];
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            foreach ($gateways as $gatewayUid => $gateway) {
                $gateway['store'] = $primaryStore['uid'];
                $projectConfig->set("commerce.gateways.$gatewayUid", $gateway);
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
        echo "m230223_105750_add_store_id_to_gateways cannot be reverted.\n";
        return false;
    }
}
