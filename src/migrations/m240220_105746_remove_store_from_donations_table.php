<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240220_105746_remove_store_from_donations_table migration.
 */
class m240220_105746_remove_store_from_donations_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists(Table::DONATIONS, 'storeId');

        $this->dropColumn(Table::DONATIONS, 'storeId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240220_105746_remove_store_from_donations_table cannot be reverted.\n";
        return false;
    }
}
