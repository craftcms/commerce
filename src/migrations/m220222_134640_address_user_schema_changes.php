<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220222_134640_address_user_schema_changes migration.
 */
class m220222_134640_address_user_schema_changes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add userId column to orders
        // add shippingAddressId column to orders

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220222_134640_address_user_schema_changes cannot be reverted.\n";
        return false;
    }
}
