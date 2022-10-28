<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m221028_192112_add_indexes_to_address_columns_on_orders migration.
 */
class m221028_192112_add_indexes_to_address_columns_on_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndexIfMissing('{{%commerce_orders}}', 'billingAddressId', false);
        $this->createIndexIfMissing('{{%commerce_orders}}', 'shippingAddressId', false);
        $this->createIndexIfMissing('{{%commerce_orders}}', 'estimatedBillingAddressId', false);
        $this->createIndexIfMissing('{{%commerce_orders}}', 'estimatedShippingAddressId', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221028_192112_add_indexes_to_address_columns_on_orders cannot be reverted.\n";
        return false;
    }
}
