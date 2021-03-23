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
        if (!$this->db->columnExists('{{commerce_discounts}}', 'userCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'userCondition', $this->string()->defaultValue('usersAnyOrNone'));
        }

        $discountAllGroups = (new Query())
            ->select(['id', 'allGroups'])
            ->from('{{%commerce_discounts}}')
            ->all();

        foreach ($discountAllGroups as $discountAllGroup) {
            $allGroups = $discountAllGroup['allGroups'];
            $userCondition = 'usersAnyOrNone';
            if ($allGroups === '0') {
                $userCondition = 'usersIncludeAny';
            }

            $this->update('{{%commerce_discounts}}', ['userCondition' => $userCondition], ['id' => $discountAllGroup['id']]);
        }

        $this->dropColumn('{{%commerce_discounts}}', 'allGroups');

        if (!$this->db->columnExists('{{commerce_sales}}', 'userCondition')) {
            $this->addColumn('{{%commerce_sales}}', 'userCondition', $this->string()->defaultValue('usersAnyOrNone'));
        }

        $saleAllGroups = (new Query())
            ->select(['id', 'allGroups'])
            ->from('{{%commerce_sales}}')
            ->all();
        
        foreach ($saleAllGroups as $saleAllGroup) {
            $allGroups = $saleAllGroup['allGroups'];
            $userCondition = 'usersAnyOrNone';
            if ($allGroups === '0') {
                $userCondition = 'usersIncludeAny';
            }

            $this->update('{{%commerce_sales}}', ['userCondition' => $userCondition], ['id' => $saleAllGroup['id']]);
        }
        
        $this->dropColumn('{{%commerce_sales}}', 'allGroups');
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
