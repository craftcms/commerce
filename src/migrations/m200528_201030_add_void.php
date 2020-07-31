<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200528_201030_add_void migration.
 */
class m200528_201030_add_void extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $values = ['authorize', 'capture', 'purchase', 'refund', 'void'];
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $check = '[[type]] in (';
            foreach ($values as $i => $value) {
                if ($i != 0) {
                    $check .= ',';
                }
                $check .= $this->db->quoteValue($value);
            }
            $check .= ')';
            $this->execute("alter table {{%commerce_transactions}} drop constraint {{%commerce_transactions_type_check}}, add check ({$check})");
        } else {
            $this->alterColumn('{{%commerce_transactions}}', 'type', $this->enum('type', $values));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200528_201030_add_void cannot be reverted.\n";
        return false;
    }
}
