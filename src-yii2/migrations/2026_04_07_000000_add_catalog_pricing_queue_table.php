<?php

use craft\commerce\db\Table;
use craft\commerce\records\CatalogPricingQueue;
use craft\db\Migration;

return new class extends Migration {
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::CATALOG_PRICING_QUEUE)) {
            $this->createTable(Table::CATALOG_PRICING_QUEUE, [
                'id' => $this->primaryKey(),
                'storeId' => $this->integer(),
                'type' => $this->enum('type', [CatalogPricingQueue::TYPE_PURCHASABLE, CatalogPricingQueue::TYPE_RULE])->notNull(),
                'ids' => $this->mediumText(),
                'reserved' => $this->boolean()->notNull()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndexIfMissing(Table::CATALOG_PRICING_QUEUE, 'reserved', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING_QUEUE, ['storeId', 'type', 'reserved'], false);
        $this->addForeignKey(null, Table::CATALOG_PRICING_QUEUE, ['storeId'], Table::STORES, ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        echo "2026_04_07_000000_add_catalog_pricing_queue_table cannot be reverted.\n";
        return false;
    }
};
