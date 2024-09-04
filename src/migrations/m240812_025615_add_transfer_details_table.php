<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240812_025615_add_transfer_details_table migration.
 */
class m240812_025615_add_transfer_details_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // drop table if exists {{%commerce_transfers_inventoryitems}}
        $this->dropTableIfExists('{{%commerce_transfers_inventoryitems}}');

        $this->createTable('{{%commerce_transferdetails}}', [
            'id' => $this->primaryKey(),
            'transferId' => $this->integer()->notNull(),
            'inventoryItemId' => $this->integer(),
            'inventoryItemDescription' => $this->string()->notNull(),
            'quantity' => $this->integer()->notNull(),
            'quantityAccepted' => $this->integer()->notNull(),
            'quantityRejected' => $this->integer()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%commerce_transferdetails}}', 'transferId', false);
        $this->createIndex(null, '{{%commerce_transferdetails}}', 'inventoryItemId', false);
        $this->addForeignKey(null, '{{%commerce_transferdetails}}', 'transferId', '{{%commerce_transfers}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_transferdetails}}', 'inventoryItemId', '{{%commerce_inventoryitems}}', 'id', 'SET NULL', 'CASCADE');

        // Add missing FK
        $this->addForeignKey(null, '{{%commerce_transfers}}', 'id', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240812_025615_add_transfer_details_table cannot be reverted.\n";
        return false;
    }
}
