<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200901_094328_add_lineItem_description_column_type migration.
 */
class m200901_094328_add_lineItem_description_column_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_lineitems}}', 'description', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200901_094328_add_lineItem_description_column_type cannot be reverted.\n";
        return false;
    }
}
