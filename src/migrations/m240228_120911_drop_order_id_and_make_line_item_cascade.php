<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240228_120911_drop_order_id_and_make_line_item_cascade migration.
 */
class m240228_120911_drop_order_id_and_make_line_item_cascade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Drop orderId from commerce_inventorytransactions
        if ($this->db->columnExists('{{%commerce_inventorytransactions}}', 'orderId')) {
            $this->dropForeignKeyIfExists('{{%commerce_inventorytransactions}}', ['orderId']);
            $this->dropColumn('{{%commerce_inventorytransactions}}', 'orderId');
        }

        // Make lineItemId cascade
        if ($this->db->columnExists('{{%commerce_inventorytransactions}}', 'lineItemId')) {
            $this->dropForeignKeyIfExists('{{%commerce_inventorytransactions}}', ['lineItemId']);
            $this->addForeignKey(null, '{{%commerce_inventorytransactions}}', 'lineItemId', '{{%commerce_lineitems}}', 'id', 'CASCADE', null);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240228_120911_drop_order_id_and_make_line_item_cascade cannot be reverted.\n";
        return false;
    }
}
