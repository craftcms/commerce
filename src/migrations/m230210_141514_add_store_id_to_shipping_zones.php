<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230210_141514_add_store_id_to_shipping_zones migration.
 */
class m230210_141514_add_store_id_to_shipping_zones extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGZONES, 'storeId', $this->integer());
        $this->createIndex(null, Table::SHIPPINGZONES, ['storeId'], false);
        $this->addForeignKey(null, Table::SHIPPINGZONES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);

        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->scalar();
        $this->update(Table::SHIPPINGZONES, ['storeId' => $primaryStoreId]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230210_141514_add_store_id_to_shipping_zones cannot be reverted.\n";
        return false;
    }
}
