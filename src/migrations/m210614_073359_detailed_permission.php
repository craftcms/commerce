<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use yii\db\Expression;

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
        $this->_detailedProducts();
        $this->_detailedPromotions();
        $this->_detailedSubscriptions();
        $this->_projectConfigUpdates();
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

    private function _projectConfigUpdates()
    {
        // Make project config updates
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '4.0', '<')) {
            $groups = (new Query())
                ->select(['id', 'name', 'uid'])
                ->from(['groups' => Table::USERGROUPS])
                ->all();

            $setGroupPermissions = [];

            foreach ($groups as $group) {
                $groupPermissions = (new Query())
                    ->select(['up.name'])
                    ->from(['up_ug' => Table::USERPERMISSIONS_USERGROUPS])
                    ->where(['up_ug.groupId' => $group['id']])
                    ->innerJoin(['up' => Table::USERPERMISSIONS], '[[up.id]] = [[up_ug.permissionId]]')
                    ->column();

                $setGroupPermissions[$group['uid']] = $groupPermissions;
            }

            foreach ($setGroupPermissions as $uid => $setGroupPermission) {
                $projectConfig->set('users.groups.' . $uid . '.permissions', $setGroupPermission);
            }
        }
    }

    private function _detailedProducts()
    {
        // Get existing manage product type permission
        $permissions = (new Query())
            ->select(['id', 'name'])
            ->from([Table::USERPERMISSIONS])
            ->where(new Expression("LEFT([[name]], 26) = 'commerce-manageproducttype'"))
            ->all();

        if (count($permissions) > 0) {

            foreach ($permissions as $permission) {

                $permissionName = explode(':', $permission['name']);
                $productTypeUid = $permissionName[1];

                // Rename manage product type to edit product type
                $newName = str_replace('commerce-manageproducttype', 'commerce-editproducttype', $permission['name']);
                $this->update(Table::USERPERMISSIONS, ['name' => $newName], ['id' => $permission['id']], [], false);

                // Create new create product permission by product type
                $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createproducts:' . $productTypeUid]);
                $createPermissionId = $this->db->getLastInsertID();

                // Create new delete product permission by product type
                $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deleteproducts:' . $productTypeUid]);
                $deletePermissionId = $this->db->getLastInsertID();

                // Check if manage product type is ticked for user permissions
                $manageProductTypes = (new Query())
                    ->select(['id', 'permissionId', 'userId'])
                    ->from([Table::USERPERMISSIONS_USERS])
                    ->where(['permissionId' => $permission['id']])
                    ->all();
                // Add the new edit product child permissions for the same users
                foreach ($manageProductTypes as $manageProductType) {
                    $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $manageProductType['userId'], 'permissionId' => $createPermissionId]);
                    $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $manageProductType['userId'], 'permissionId' => $deletePermissionId]);
                }

                // Check if manage product type is ticked for user group permissions
                $manageProductTypesForGroups = (new Query())
                    ->select(['id', 'permissionId', 'groupId'])
                    ->from([Table::USERPERMISSIONS_USERGROUPS])
                    ->where(['permissionId' => $permission['id']])
                    ->all();
                // Add the new edit product child permissions for the same groups
                foreach ($manageProductTypesForGroups as $manageProductType) {
                    // Create new create and delete product permission relationship with a group.
                    $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $manageProductType['groupId'], 'permissionId' => $createPermissionId]);
                    $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $manageProductType['groupId'], 'permissionId' => $deletePermissionId]);
                }
            }

            // No longer need this top level permission
            $this->delete(Table::USERPERMISSIONS, ['name' => 'commerce-manageproducts']);
        }
    }
}
