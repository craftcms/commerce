<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m240313_131445_tidy_shipping_methods migration.
 */
class m240313_131445_tidy_shipping_methods extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $storeIds = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->column();

        $this->delete(Table::SHIPPINGMETHODS, ['not', ['storeId' => $storeIds]]);

        $this->dropForeignKeyIfExists(Table::SHIPPINGMETHODS, ['storeId']);

        $this->alterColumn(Table::SHIPPINGMETHODS, 'storeId', $this->integer()->notNull());

        $this->addForeignKey(null, Table::SHIPPINGMETHODS, ['storeId'], Table::STORES, ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240313_131445_tidy_shipping_methods cannot be reverted.\n";
        return false;
    }
}
