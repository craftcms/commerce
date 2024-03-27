<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230208_130445_add_store_id_to_shipping_categories migration.
 */
class m230208_130445_add_store_id_to_shipping_categories extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGCATEGORIES, 'storeId', $this->integer());
        $this->createIndex(null, Table::SHIPPINGCATEGORIES, ['storeId'], false);
        $this->addForeignKey(null, Table::SHIPPINGCATEGORIES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);

        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->scalar();
        $this->update(Table::SHIPPINGCATEGORIES, ['storeId' => $primaryStoreId]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230208_130445_add_store_id_to_shipping_categories cannot be reverted.\n";
        return false;
    }
}
