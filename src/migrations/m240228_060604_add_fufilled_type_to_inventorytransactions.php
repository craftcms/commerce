<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240228_060604_add_fufilled_column_to_inventorytransactions migration.
 */
class m240228_060604_add_fufilled_type_to_inventorytransactions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%commerce_inventorytransactions}}', 'type', $this->enum('type', ['available', 'reserved', 'damaged', 'safety', 'qualityControl', 'committed', 'fulfilled', 'incoming'])->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240228_060604_add_fufilled_column_to_inventorytransactions cannot be reverted.\n";
        return false;
    }
}
