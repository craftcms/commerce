<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240301_113924_add_line_item_types migration.
 */
class m240301_113924_add_line_item_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::LINEITEMS, 'type', $this->enum('type', [
            'purchasable',
            'custom',
        ])->defaultValue('purchasable')->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240301_113924_add_line_item_types cannot be reverted.\n";
        return false;
    }
}
