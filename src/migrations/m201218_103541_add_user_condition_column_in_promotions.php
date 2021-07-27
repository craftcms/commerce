<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

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
        if (!$this->db->columnExists('{{%commerce_discounts}}', 'userGroupsCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'userGroupsCondition', $this->string()->defaultValue('userGroupsAnyOrNone'));
        }

        // Where we has allGroups we now use userGroupsAnyOrNone
        $this->update('{{%commerce_discounts}}', ['userGroupsCondition' => 'userGroupsAnyOrNone'], ['allGroups' => true]);

        // When we had groups listed, they were an any condition originally
        $this->update('{{%commerce_discounts}}', ['userGroupsCondition' => 'userGroupsIncludeAny'], ['allGroups' => false]);

        $this->dropColumn('{{%commerce_discounts}}', 'allGroups');
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
