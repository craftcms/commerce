<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m210616_152013_change_return_cancel_url_column_type migration.
 */
class m210616_152013_change_return_cancel_url_column_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Running this migration again as a new migration since the column type was wrong in the install migration
        $this->alterColumn('{{%commerce_orders}}', 'returnUrl', $this->text());
        $this->alterColumn('{{%commerce_orders}}', 'cancelUrl', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210616_152013_change_return_cancel_url_column_type cannot be reverted.\n";
        return false;
    }
}
