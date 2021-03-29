<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use Craft;

/**
 * m210317_093137_add_type_to_notices migration.
 */
class m210317_093137_add_type_to_notices extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_ordernotices}}', 'type')) {
            $this->addColumn('{{%commerce_ordernotices}}', 'type', $this->string()->after('orderId'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210317_093136_includedTax_fix cannot be reverted.\n";
        return false;
    }
}