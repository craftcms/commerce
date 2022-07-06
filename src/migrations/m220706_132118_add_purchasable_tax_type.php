<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m220706_132118_add_purchasable_tax_type migration.
 */
class m220706_132118_add_purchasable_tax_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $values = ['purchasable', 'price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price'];
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $check = '[[taxable]] in (';
            foreach ($values as $i => $value) {
                if ($i != 0) {
                    $check .= ',';
                }
                $check .= $this->db->quoteValue($value);
            }
            $check .= ')';
            $this->execute("alter table {{%commerce_taxrates}} drop constraint {{%commerce_taxrates_taxable_check}}, add check ({$check})");
        } else {
            $this->alterColumn('{{%commerce_taxrates}}', 'taxable', $this->enum('taxable', $values));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220706_132118_add_purchasable_tax_type cannot be reverted.\n";
        return false;
    }
}
