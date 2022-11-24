<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m221124_114239_add_date_deleted_to_stores migration.
 */
class m221124_114239_add_date_deleted_to_stores extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // add dateDeleted to stores table
        if (!$this->db->columnExists('{{%commerce_stores}}', 'dateDeleted')) {
            $this->addColumn('{{%commerce_stores}}', 'dateDeleted', $this->dateTime()->null());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221124_114239_add_date_deleted_to_stores cannot be reverted.\n";
        return false;
    }
}
