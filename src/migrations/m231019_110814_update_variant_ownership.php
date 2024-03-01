<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;

/**
 * m231019_110814_update_variant_ownership migration.
 */
class m231019_110814_update_variant_ownership extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get existing variant data
        $data = (new Query())
            ->select([
                'id as elementId',
                'productId as ownerId',
                'sortOrder',
            ])
            ->from([Table::VARIANTS])
            ->all();

        // Insert data in element owners
        $this->batchInsert(CraftTable::ELEMENTS_OWNERS, ['elementId', 'ownerId', 'sortOrder'], $data);

        // Rename `productId` column
        $this->renameColumn(Table::VARIANTS, 'productId', 'primaryOwnerId');

        // Remove sort order
        $this->dropColumn(Table::VARIANTS, 'sortOrder');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231019_110814_update_variant_ownership cannot be reverted.\n";
        return false;
    }
}
