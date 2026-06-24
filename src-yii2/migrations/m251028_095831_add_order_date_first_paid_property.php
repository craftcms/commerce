<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m251028_095831_add_order_date_first_paid_property migration.
 */
class m251028_095831_add_order_date_first_paid_property extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::ORDERS, 'dateFirstPaid', $this->dateTime()->after('datePaid'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251028_095831_add_order_date_first_paid_property cannot be reverted.\n";
        return false;
    }
}
