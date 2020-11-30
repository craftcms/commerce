<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use yii\db\Expression;

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

            // Make project config updates
            $projectConfig = Craft::$app->getProjectConfig();
            $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
            if (version_compare($schemaVersion, '3.2.10', '<')) {

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
