<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230530_100604_add_complete_email_column migration.
 */
class m230530_100604_add_complete_email_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add new column
        $this->addColumn(Table::ORDERS, 'orderCompletedEmail', $this->string());

        // Update existing data
        $this->update(Table::ORDERS, ['orderCompletedEmail' => '[[email]]'], ['isCompleted' => true], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230530_100604_add_complete_email_column cannot be reverted.\n";
        return false;
    }
}
