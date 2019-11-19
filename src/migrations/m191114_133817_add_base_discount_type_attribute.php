<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191114_133817_add_base_discount_type_attribute migration.
 */
class m191114_133817_add_base_discount_type_attribute extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $columnName = 'baseDiscountType';
        $values = ['value', 'percentTotal', 'percentTotalDiscounted', 'percentItems', 'percentItemsDiscounted'];

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $check = '[['.$columnName.']] in (';
            foreach ($values as $i => $value) {
                if ($i != 0) {
                    $check .= ',';
                }
                $check .= $this->db->quoteValue($value);
            }
            $check .= ')';
            $this->execute("alter table {{%commerce_discounts}} drop constraint {{%commerce_discounts_".$columnName."_check}}, add check ({$check})");
        } else {
            $this->addColumn('{{%commerce_discounts}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('value'));
        }

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191114_133817_add_base_discount_type_attribute cannot be reverted.\n";
        return false;
    }
}
