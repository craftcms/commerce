<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240221_030027_transfer_items migration.
 */
class m240221_030027_transfer_items extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // create the transfer items table
        $this->createTable('{{%commerce_transfers_inventoryitems}}', [
            'id' => $this->primaryKey(),
            'transferId' => $this->integer()->notNull(),
            'inventoryItemId' => $this->integer()->notNull(),
            'quantity' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%commerce_transfers_inventoryitems}}', 'inventoryItemId', false);
        $this->createIndex(null, '{{%commerce_transfers_inventoryitems}}', 'transferId', false);

        $this->addForeignKey(null, '{{%commerce_transfers_inventoryitems}}', ['inventoryItemId'], '{{%commerce_inventoryitems}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_transfers_inventoryitems}}', ['transferId'], '{{%commerce_inventoryitems}}', ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240221_030027_transfer_items cannot be reverted.\n";
        return false;
    }
}
