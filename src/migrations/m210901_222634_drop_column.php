<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m210901_222634_drop_column migration.
 */
class m210901_222634_drop_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->columnExists(Table::GATEWAYS, 'sendCartInfo')) {
            $this->dropColumn(Table::GATEWAYS, 'sendCartInfo');
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210901_222634_drop_column cannot be reverted.\n";
        return false;
    }
}
