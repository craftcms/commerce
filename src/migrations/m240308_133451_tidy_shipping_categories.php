<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m240308_133451_tidy_shipping_categories migration.
 */
class m240308_133451_tidy_shipping_categories extends Migration
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

        $this->delete(Table::SHIPPINGCATEGORIES, ['not', ['storeId' => $storeIds]]);

        $this->dropForeignKeyIfExists(Table::SHIPPINGCATEGORIES, ['storeId']);

        $this->alterColumn(Table::SHIPPINGCATEGORIES, 'storeId', $this->integer()->notNull());
        
        $this->addForeignKey(null, Table::SHIPPINGCATEGORIES, ['storeId'], Table::STORES, ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240308_133451_tidy_shipping_categories cannot be reverted.\n";
        return false;
    }
}
