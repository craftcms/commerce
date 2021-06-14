<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use yii\db\Expression;

/**
 * m210614_073359_discount_detailed_permission migration.
 */
class m210614_073359_promotion_detailed_permission extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Create new promotion permissions
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-editSales']);
        $editSalesId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createSales']);
        $createSalesId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deleteSales']);
        $deleteSalesId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-editDiscounts']);
        $editDiscountsId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createDiscounts']);
        $createDiscountsId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deleteDiscounts']);
        $deleteDiscountsId = $this->db->getLastInsertID();
        
        $permissionId = (new Query())
            ->select(['id'])
            ->from([Table::USERPERMISSIONS])
            ->where(['name' => 'commerce-managepromotions'])
            ->scalar();

        $userPromotions = (new Query())
            ->select(['id', 'userId'])
            ->from([Table::USERPERMISSIONS_USERS])
            ->where(['permissionId' => $permissionId])
            ->all();
        
        foreach ($userPromotions as $userPromotion) {
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userPromotion['userId'], 'permissionId' => $editSalesId]);
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userPromotion['userId'], 'permissionId' => $createSalesId]);
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userPromotion['userId'], 'permissionId' => $deleteSalesId]);            
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userPromotion['userId'], 'permissionId' => $editDiscountsId]);
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userPromotion['userId'], 'permissionId' => $createDiscountsId]);
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userPromotion['userId'], 'permissionId' => $deleteDiscountsId]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210614_073359_discount_detailed_permission cannot be reverted.\n";
        return false;
    }
}
