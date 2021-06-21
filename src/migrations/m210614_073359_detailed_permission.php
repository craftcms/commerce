<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m210614_073359_detailed_permission migration.
 */
class m210614_073359_detailed_permission extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->_detailedPromotions();
        $this->_detailedSubscriptions();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210614_073359_detailed_permission cannot be reverted.\n";
        return false;
    }
    
    private function _detailedPromotions()
    {
        // Create new promotion permissions
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-editsales']);
        $editSalesId = $this->db->getLastInsertID();

        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createsales']);
        $createSalesId = $this->db->getLastInsertID();

        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deletesales']);
        $deleteSalesId = $this->db->getLastInsertID();

        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-editdiscounts']);
        $editDiscountsId = $this->db->getLastInsertID();

        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-creatediscounts']);
        $createDiscountsId = $this->db->getLastInsertID();

        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deletediscounts']);
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

        // Check if manage product type is ticked for user group permissions
        $groupPromotions = (new Query())
            ->select(['id', 'permissionId', 'groupId'])
            ->from([Table::USERPERMISSIONS_USERGROUPS])
            ->where(['permissionId' => $permissionId])
            ->all();

        foreach ($groupPromotions as $groupPromotion) {
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupPromotion['groupId'], 'permissionId' => $editSalesId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupPromotion['groupId'], 'permissionId' => $createSalesId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupPromotion['groupId'], 'permissionId' => $deleteSalesId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupPromotion['groupId'], 'permissionId' => $editDiscountsId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupPromotion['groupId'], 'permissionId' => $createDiscountsId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupPromotion['groupId'], 'permissionId' => $deleteDiscountsId]);
        }
    }

    private function _detailedSubscriptions()
    {
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-editsubscriptions']);
        $editSubscriptionId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createsubscriptions']);
        $createSubscriptionId = $this->db->getLastInsertID();        
        
        $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deletesubscriptions']);
        $deleteSubscriptionId = $this->db->getLastInsertID();

        $permissionId = (new Query())
            ->select(['id'])
            ->from([Table::USERPERMISSIONS])
            ->where(['name' => 'commerce-managesubscriptions'])
            ->scalar();

        $userSubscriptions = (new Query())
            ->select(['id', 'userId'])
            ->from([Table::USERPERMISSIONS_USERS])
            ->where(['permissionId' => $permissionId])
            ->all();

        foreach ($userSubscriptions as $userSubscription) {
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userSubscription['userId'], 'permissionId' => $editSubscriptionId]);
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userSubscription['userId'], 'permissionId' => $createSubscriptionId]);
            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $userSubscription['userId'], 'permissionId' => $deleteSubscriptionId]);
        }

        $groupSubscriptions = (new Query())
            ->select(['id', 'permissionId', 'groupId'])
            ->from([Table::USERPERMISSIONS_USERGROUPS])
            ->where(['permissionId' => $permissionId])
            ->all();

        foreach ($groupSubscriptions as $groupSubscription) {
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupSubscription['groupId'], 'permissionId' => $editSubscriptionId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupSubscription['groupId'], 'permissionId' => $createSubscriptionId]);
            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $groupSubscription['groupId'], 'permissionId' => $deleteSubscriptionId]);
        }

    }
}
