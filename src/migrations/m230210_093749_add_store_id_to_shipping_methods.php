<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230210_093749_add_store_id_to_shipping_methods migration.
 */
class m230210_093749_add_store_id_to_shipping_methods extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGMETHODS, 'storeId', $this->integer());
        $this->createIndex(null, Table::SHIPPINGMETHODS, ['storeId'], false);
        $this->addForeignKey(null, Table::SHIPPINGMETHODS, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);

        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->scalar();
        $this->update(Table::SHIPPINGMETHODS, ['storeId' => $primaryStoreId]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230210_093749_add_store_id_to_shipping_methods cannot be reverted.\n";
        return false;
    }
}
