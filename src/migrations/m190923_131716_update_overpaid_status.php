<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
        $values = ['paid', 'partial', 'unpaid', 'overPaid'];
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $check = '[[paidStatus]] in (';
            foreach ($values as $i => $value) {
                if ($i != 0) {
                    $check .= ',';
                }
                $check .= $this->db->quoteValue($value);
            }
            $check .= ')';
            $this->execute("alter table {{%commerce_orders}} drop constraint {{%commerce_orders_paidStatus_check}}, add check ({$check})");
        } else {
            $this->alterColumn('{{%commerce_orders}}', 'paidStatus', $this->enum('paidStatus', $values));
        }
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
