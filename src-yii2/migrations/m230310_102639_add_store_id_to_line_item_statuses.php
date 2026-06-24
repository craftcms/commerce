<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230310_102639_add_store_id_to_line_item_statuses migration.
 */
class m230310_102639_add_store_id_to_line_item_statuses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::LINEITEMSTATUSES, 'storeId', $this->integer());

        $primaryStore = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->one();

        $this->update(Table::LINEITEMSTATUSES, ['storeId' => $primaryStore['id']], ['storeId' => null], [], false);

        $this->addForeignKey(null, Table::LINEITEMSTATUSES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::LINEITEMSTATUSES, ['storeId'], false);

        $projectConfig = Craft::$app->getProjectConfig();

        $lineItemStatuses = $projectConfig->get('commerce.lineItemStatuses') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($lineItemStatuses as $statusUid => $lineItemStatus) {
            $lineItemStatus['store'] = $primaryStore['uid'];
            $projectConfig->set("commerce.lineItemStatuses.$statusUid", $lineItemStatus);
        }

        $projectConfig->muteEvents = $muteEvents;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230310_102639_add_store_id_to_line_item_statuses cannot be reverted.\n";
        return false;
    }
}
