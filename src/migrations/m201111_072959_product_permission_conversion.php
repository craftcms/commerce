<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m201111_072959_product_permission_conversion migration.
 */
class m201111_072959_product_permission_conversion extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Get existing manage product type permission
        $permissions = (new Query())
            ->select(['id', 'name'])
            ->from([Table::USERPERMISSIONS])
            ->where(['like', 'name', 'commerce-manageproducttype'])
            ->all();

        if (count($permissions) > 0) {
            
            foreach ($permissions as $permission) {
                
                $permissionName = explode(':', $permission['name']);
                $productTypeUid = $permissionName[1];
                
                // Change manage product type to edit product type
                $newName = str_replace('commerce-manageproducttype', 'commerce-editproducttype', $permission['name']);
                $this->update(Table::USERPERMISSIONS, ['name' => $newName], ['id' => $permission['id']], [], false);
                
                // Create new create product permission by product type
                $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createproducts:' . $productTypeUid]);
                $createPermissionId = $this->db->getLastInsertID();
                
                // Create new delete product permission by product type
                $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deleteproducts:' . $productTypeUid]);
                $deletePermissionId = $this->db->getLastInsertID();
                
                // Check if manage product type is ticked for user permission
                $manageProductTypes = (new Query())
                    ->select(['id', 'permissionId', 'userId'])
                    ->from([Table::USERPERMISSIONS_USERS])
                    ->where(['permissionId' => $permission['id']])
                    ->all();
                
                
                if (count($manageProductTypes) > 0) {
                    foreach ($manageProductTypes as $manageProductType) {
                        if ($manageProductType !== null) {
                            // Create new create and delete product permission relationship with user.
                            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $manageProductType['userId'], 'permissionId' => $createPermissionId]);
                            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $manageProductType['userId'], 'permissionId' => $deletePermissionId]);
                        }
                    }
                }                
                
                // Check if manage product type is ticked for user permission
                $manageProductTypes = (new Query())
                    ->select(['id', 'permissionId', 'groupId'])
                    ->from([Table::USERPERMISSIONS_USERGROUPS])
                    ->where(['permissionId' => $permission['id']])
                    ->all();
                
                
                if (count($manageProductTypes) > 0) {
                    foreach ($manageProductTypes as $manageProductType) {
                        if ($manageProductType !== null) {
                            // Create new create and delete product permission relationship with a group.
                            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $manageProductType['groupId'], 'permissionId' => $createPermissionId]);
                            $this->insert(Table::USERPERMISSIONS_USERGROUPS, ['groupId' => $manageProductType['groupId'], 'permissionId' => $deletePermissionId]);
                        }
                    }
                }
            }
        }
        
        $this->delete(Table::USERPERMISSIONS, ['name' => 'commerce-manageproducts']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201111_072959_product_permission_conversion cannot be reverted.\n";
        return false;
    }
}
