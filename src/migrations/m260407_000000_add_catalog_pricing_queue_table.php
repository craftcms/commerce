<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m260407_000000_add_catalog_pricing_queue_table migration.
 */
class m260407_000000_add_catalog_pricing_queue_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::CATALOG_PRICING_QUEUE)) {
            $this->createTable(Table::CATALOG_PRICING_QUEUE, [
                'id' => $this->primaryKey(),
                'storeId' => $this->integer(),
                'type' => $this->string(16)->notNull(),
                'ids' => $this->mediumText(),
                'reserved' => $this->boolean()->notNull()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing(Table::CATALOG_PRICING_QUEUE, 'reserved', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING_QUEUE, ['storeId', 'type', 'reserved'], false);
        $this->addForeignKeyIfMissing(Table::CATALOG_PRICING_QUEUE, ['storeId'], Table::STORES, ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260407_000000_add_catalog_pricing_queue_table cannot be reverted.\n";
        return false;
    }
}
