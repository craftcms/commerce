<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m221206_094303_add_store_to_order migration.
 */
class m221206_094303_add_store_to_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get the current primary ID for existing orders
        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_stores}}'])
            ->where(['primary' => true])
            ->scalar();

        // Add storeId to order table
        if (!$this->db->columnExists('{{%commerce_orders}}', 'storeId')) {
            $this->addColumn('{{%commerce_orders}}', 'storeId', $this->integer()->after('id')->defaultValue($primaryStoreId)->notNull());
            $this->addForeignKey(null, '{{%commerce_orders}}', ['storeId'], '{{%commerce_stores}}', ['id'], 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%commerce_orders}}', ['storeId'], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221206_094303_add_store_to_order cannot be reverted.\n";
        return false;
    }
}
