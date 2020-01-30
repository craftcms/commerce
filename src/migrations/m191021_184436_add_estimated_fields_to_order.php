<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191021_184436_add_estimated_fields_to_order migration.
 */
class m191021_184436_add_estimated_fields_to_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orders}}', 'estimatedBillingAddressId', $this->integer());
        $this->addColumn('{{%commerce_orders}}', 'estimatedShippingAddressId', $this->integer());

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'estimatedBillingAddressId'), '{{%commerce_orders}}', 'estimatedBillingAddressId', '{{%commerce_addresses}}', 'id', 'SET NULL');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'estimatedShippingAddressId'), '{{%commerce_orders}}', 'estimatedShippingAddressId', '{{%commerce_addresses}}', 'id', 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191021_184436_add_estimated_fields_to_order cannot be reverted.\n";
        return false;
    }
}
