<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190924_184909_taxCategory_allow_null_postgres migration.
 */
class m190924_184909_taxCategory_allow_null_postgres extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $this->execute('alter table {{%commerce_taxrates}} alter column [[taxCategoryId]] drop not null');
        } else {
            $this->alterColumn('{{%commerce_taxrates}}', 'taxCategoryId', $this->integer()->null());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190924_184909_taxCategory_allow_null_postgres cannot be reverted.\n";
        return false;
    }
}
