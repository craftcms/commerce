<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230308_084340_add_store_id_to_order_statuses migration.
 */
class m230308_084340_add_store_id_to_order_statuses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::ORDERSTATUSES, 'storeId', $this->integer());

        $primaryStore = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->one();

        $this->update(Table::ORDERSTATUSES, ['storeId' => $primaryStore['id']], ['storeId' => null], [], false);

        $this->addForeignKey(null, Table::ORDERSTATUSES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::ORDERSTATUSES, ['storeId'], false);

        $projectConfig = Craft::$app->getProjectConfig();

        $orderStatuses = $projectConfig->get('commerce.orderStatuses') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($orderStatuses as $statusUid => $orderStatus) {
            $orderStatus['store'] = $primaryStore['uid'];
            $projectConfig->set("commerce.orderStatuses.$statusUid", $orderStatus);
        }

        $projectConfig->muteEvents = $muteEvents;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230308_084340_add_store_id_to_order_statuses cannot be reverted.\n";
        return false;
    }
}
