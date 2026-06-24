<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m241219_071723_add_inventory_backorder migration.
 */
class m241219_071723_add_inventory_backorder extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::PURCHASABLES_STORES, 'allowOutOfStockPurchases')) {
            $this->addColumn(Table::PURCHASABLES_STORES, 'allowOutOfStockPurchases', $this->boolean()->after('inventoryTracked')->notNull()->defaultValue(false));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241219_071723_add_inventory_backorder cannot be reverted.\n";
        return false;
    }
}
