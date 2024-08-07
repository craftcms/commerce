<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240718_073046_remove_sortOrder_variants_column_if_exists migration.
 */
class m240718_073046_remove_sortOrder_variants_column_if_exists extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%commerce_variants}}', 'sortOrder')) {
            $this->dropColumn('{{%commerce_variants}}', 'sortOrder');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240718_073046_remove_sortOrder_variants_column_if_exists cannot be reverted.\n";
        return false;
    }
}
