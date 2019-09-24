<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190923_131716_update_overpaid_status migration.
 */
class m190923_131716_update_overpaid_status extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_orders}}', 'paidStatus', $this->enum('paidStatus', ['paid', 'partial', 'unpaid', 'overPaid']));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190923_131716_update_overpaid_status cannot be reverted.\n";
        return false;
    }
}
