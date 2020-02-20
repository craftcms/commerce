<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200206_161706_date_authorized migration.
 */
class m200206_161706_date_authorized extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orders}}', 'dateAuthorized', $this->dateTime());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200206_161706_date_authorized cannot be reverted.\n";
        return false;
    }
}
