<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m200320_161708_add_index_order_email migration.
 */
class m200320_161708_add_index_order_email extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!MigrationHelper::doesIndexExist(Table::ORDERS, 'email', false)) {
            $this->createIndex(null, Table::ORDERS, 'email', false);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200320_161708_add_index_order_email cannot be reverted.\n";
        return false;
    }
}
