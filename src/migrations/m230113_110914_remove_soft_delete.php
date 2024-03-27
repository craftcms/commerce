<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m230113_110914_remove_soft_delete migration.
 */
class m230113_110914_remove_soft_delete extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%commerce_stores}}', 'dateDeleted')) {
            $this->dropColumn('{{%commerce_stores}}', 'dateDeleted');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230113_110914_remove_soft_delete cannot be reverted.\n";
        return false;
    }
}
