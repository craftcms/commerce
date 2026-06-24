<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\db\Migration;

/**
 * m250210_125139_fix_cart_recalculation_modes migration.
 */
class m250210_125139_fix_cart_recalculation_modes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->update(Table::ORDERS, ['recalculationMode' => Order::RECALCULATION_MODE_ALL], [
            'recalculationMode' => Order::RECALCULATION_MODE_NONE,
            'isCompleted' => false,
        ], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250210_125139_fix_cart_recalculation_modes cannot be reverted.\n";
        return false;
    }
}
