<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191016_231143_add_sortOrder_to_countries migration.
 */
class m191016_231143_add_sortOrder_to_countries extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_countries}}', 'sortOrder', $this->integer()->after('isStateRequired'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191016_231143_add_sortOrder_to_countries cannot be reverted.\n";
        return false;
    }
}
