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
        $permissions = (new Query())
            ->select(['id', 'name'])
            ->from([Table::USERPERMISSIONS])
            ->where(['like', 'name', 'commerce-manageproducttype'])
            ->all();

        if (count($permissions) > 0) {
            
            foreach ($permissions as $permission) {
                
                $permissionName = explode(':', $permission['name']);
                $productTypeUid = $permissionName[1];
               
                $newName = str_replace('commerce-manageproducttype', 'commerce-editproducttype', $permission['name']);

             
                $this->update(Table::USERPERMISSIONS, ['name' => $newName], ['id' => $permission['id']], [], false);
                $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-createproducts:' . $productTypeUid]);
                $createPermissionId = $this->db->getLastInsertID();
                
                $this->insert(Table::USERPERMISSIONS, ['name' => 'commerce-deleteproducts:' . $productTypeUid]);
                $deletePermissionId = $this->db->getLastInsertID();
                
                $manageProductTypes = (new Query())
                    ->select(['id', 'permissionId', 'userId'])
                    ->from([Table::USERPERMISSIONS_USERS])
                    ->where(['permissionId' => $permission['id']])
                    ->all();
                
                // Check if manage product is ticked.
                if (count($manageProductTypes) > 0) {
                    foreach ($manageProductTypes as $manageProductType) {
               
                        if ($manageProductType !== null) {
                            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $manageProductType['userId'], 'permissionId' => $createPermissionId]);
                            
                            $this->insert(Table::USERPERMISSIONS_USERS, ['userId' => $manageProductType['userId'], 'permissionId' => $deletePermissionId]);
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
