<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m210114_093299_add_orderConditionFormula_to_shipping_rules migration.
 */
class m210114_093299_add_orderConditionFormula_to_shipping_rules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_shippingrules}}', 'orderConditionFormula')) {
            $this->addColumn(
                '{{%commerce_shippingrules}}',
                'orderConditionFormula',
                $this->text()
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210114_093299_add_orderConditionFormula_to_shipping_rules cannot be reverted.\n";
        return false;
    }
}
