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
        if (!$this->db->columnExists('{{commerce_discounts}}', 'userGroupsCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'userGroupsCondition', $this->string()->defaultValue('userGroupsAnyOrNone'));
        }

        $discountAllGroups = (new Query())
            ->select(['id', 'allGroups'])
            ->from('{{%commerce_discounts}}')
            ->all();

        foreach ($discountAllGroups as $discountAllGroup) {
            $allGroups = $discountAllGroup['allGroups'];
            $userGroupsCondition = 'userGroupsAnyOrNone';
            if (!$allGroups) {
                $userGroupsCondition = 'userGroupsIncludeAny';
            }

            $this->update('{{%commerce_discounts}}', ['userGroupsCondition' => $userGroupsCondition], ['id' => $discountAllGroup['id']]);
        }

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
