<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m260505_071943_add_orders_customerDeleted_column migration.
 */
class m260505_071943_add_orders_customerDeleted_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add the `customerDeleted` boolean column to the `orders` table
        if ($this->getDb()->columnExists(Table::ORDERS, 'customerDeleted')) {
            return true;
        }

        $this->addColumn(Table::ORDERS, 'customerDeleted', $this->boolean()->notNull()->defaultValue(false)->after('customerId'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260505_071943_add_orders_customerDeleted_column cannot be reverted.\n";
        return false;
    }
}
