<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200402_172412_add_order_condition_formula migration.
 */
class m200402_172412_add_order_condition_formula extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_discounts}}', 'orderConditionFormula')) {
            $this->addColumn('{{%commerce_discounts}}', 'orderConditionFormula', $this->text()->after('categoryRelationshipType'));
        } else {
            $this->alterColumn('{{%commerce_discounts}}', 'orderConditionFormula', $this->text());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200402_172412_add_order_condition_formula cannot be reverted.\n";
        return false;
    }
}
