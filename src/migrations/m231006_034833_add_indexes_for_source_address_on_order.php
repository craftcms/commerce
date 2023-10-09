<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m231006_034833_add_indexes_for_source_address_on_order migration.
 */
class m231006_034833_add_indexes_for_source_address_on_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndex(null, Table::ORDERS, 'sourceBillingAddressId', false);
        $this->createIndex(null, Table::ORDERS, 'sourceShippingAddressId', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231006_034833_add_indexes_for_source_address_on_order cannot be reverted.\n";
        return false;
    }
}
