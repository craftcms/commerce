<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230307_091520_add_sort_order_to_stores migration.
 */
class m230307_091520_add_sort_order_to_stores extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::STORES, 'sortOrder', $this->integer());

        $this->update(Table::STORES, ['sortOrder' => 1], ['primary' => true], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230307_091520_add_sort_order_to_stores cannot be reverted.\n";
        return false;
    }
}
