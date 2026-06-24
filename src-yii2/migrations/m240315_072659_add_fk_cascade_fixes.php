<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\MigrationHelper;

/**
 * m240315_072659_add_fk_cascade_fixes migration.
 */
class m240315_072659_add_fk_cascade_fixes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addForeignKey(null, Table::STORESETTINGS, ['locationAddressId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');

        // There is no ability to drop the FK without knowing its name.
        MigrationHelper::dropAllForeignKeysOnTable(Table::INVENTORYLOCATIONS);
        $this->addForeignKey(null, Table::INVENTORYLOCATIONS, 'addressId', CraftTable::ELEMENTS, 'id', 'CASCADE', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240315_072659_add_fk_cascade_fixes cannot be reverted.\n";
        return false;
    }
}
