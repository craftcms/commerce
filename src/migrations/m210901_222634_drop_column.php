<?php

namespace craft\commerce\migrations;

use Craft;
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
        if ($this->db->columnExists('{{%commerce_gateways}}', 'sendCartInfo')) {
            $this->dropColumn('{{%commerce_gateways}}', 'sendCartInfo');
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
