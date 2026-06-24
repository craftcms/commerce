<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230705_124845_add_save_address_columns migration.
 */
class m230705_124845_add_save_address_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::ORDERS, 'saveBillingAddressOnOrderComplete', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn(Table::ORDERS, 'saveShippingAddressOnOrderComplete', $this->boolean()->notNull()->defaultValue(false));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230705_124845_add_save_address_columns cannot be reverted.\n";
        return false;
    }
}
