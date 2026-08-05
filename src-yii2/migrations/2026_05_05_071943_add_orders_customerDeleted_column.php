<?php

use craft\commerce\db\Table;
use craft\db\Migration;

return new class extends Migration {
    public function safeUp(): bool
    {
        // Add the `customerDeleted` boolean column to the `orders` table
        if ($this->getDb()->columnExists(Table::ORDERS, 'customerDeleted')) {
            return true;
        }

        $this->addColumn(Table::ORDERS, 'customerDeleted', $this->boolean()->notNull()->defaultValue(false)->after('customerId'));

        return true;
    }

    public function safeDown(): bool
    {
        echo "2026_05_05_071943_add_orders_customerDeleted_column cannot be reverted.\n";
        return false;
    }
};
