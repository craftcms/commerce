<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240228_054005_rename_movements_table migration.
 */
class m240228_054005_rename_movements_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // rename inventory movements to inventory transactions
        $this->renameTable('{{%commerce_inventorymovements}}', '{{%commerce_inventorytransactions}}');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240228_054005_rename_movements_table cannot be reverted.\n";
        return false;
    }
}
