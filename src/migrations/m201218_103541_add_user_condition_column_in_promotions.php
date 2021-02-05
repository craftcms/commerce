<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m201218_103541_add_user_condition_column_in_promotions migration.
 */
class m201218_103541_add_user_condition_column_in_promotions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{commerce_discounts}}', 'userCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'userCondition', $this->string()->defaultValue('usersAnyOrNone'));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201218_103541_add_user_condition_column_in_promotions cannot be reverted.\n";
        return false;
    }
}
