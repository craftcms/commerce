<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250128_083515_add_make_primary_addresses_to_orders migration.
 */
class m250128_083515_add_make_primary_addresses_to_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::ORDERS, 'makePrimaryShippingAddress', $this->boolean()->defaultValue(false)->after('saveShippingAddressOnOrderComplete'));
        $this->addColumn(Table::ORDERS, 'makePrimaryBillingAddress', $this->boolean()->defaultValue(false)->after('saveBillingAddressOnOrderComplete'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250128_083515_add_make_primary_addresses_to_orders cannot be reverted.\n";
        return false;
    }
}
