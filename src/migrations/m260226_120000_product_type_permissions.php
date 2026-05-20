<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m260226_120000_product_type_permissions migration.
 *
 * Migrates product type permissions from the old naming scheme to the new one:
 * - commerce-editProductType:{uid} → commerce-viewProductType:{uid} + commerce-saveProductType:{uid}
 * - commerce-createProducts:{uid} → commerce-createProductType:{uid}
 * - commerce-deleteProducts:{uid} → commerce-deleteProductType:{uid}
 */
class m260226_120000_product_type_permissions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get all product type UIDs from the database
        $productTypeUids = (new Query())
            ->select(['uid'])
            ->from('{{%commerce_producttypes}}')
            ->column($this->db);

        // Build the permission mapping
        $map = []; // oldPermission => [newPermission, ...]
        foreach ($productTypeUids as $uid) {
            // commerce-editProductType → commerce-viewProductType + commerce-saveProductType
            $map[strtolower("commerce-editProductType:$uid")] = [
                strtolower("commerce-viewProductType:$uid"),
                strtolower("commerce-saveProductType:$uid"),
            ];
            // commerce-createProducts → commerce-createProductType
            $map[strtolower("commerce-createProducts:$uid")] = [
                strtolower("commerce-createProductType:$uid"),
            ];
            // commerce-deleteProducts → commerce-deleteProductType
            $map[strtolower("commerce-deleteProducts:$uid")] = [
                strtolower("commerce-deleteProductType:$uid"),
            ];
        }

        // Migrate user permissions in the database
        foreach ($map as $oldPermission => $newPermissions) {
            // Find all users with the old permission
            $userIds = (new Query())
                ->select(['upu.userId'])
                ->from(['upu' => Table::USERPERMISSIONS_USERS])
                ->innerJoin(['up' => Table::USERPERMISSIONS], '[[up.id]] = [[upu.permissionId]]')
                ->where(['up.name' => $oldPermission])
                ->column($this->db);

            $userIds = array_unique($userIds);

            if (!empty($userIds)) {
                foreach ($newPermissions as $newPermission) {
                    // Delete the permission if it already exists
                    $this->delete(Table::USERPERMISSIONS, [
                        'name' => $newPermission,
                    ]);

                    $this->insert(Table::USERPERMISSIONS, [
                        'name' => $newPermission,
                    ]);
                    $newPermissionId = $this->db->getLastInsertID(Table::USERPERMISSIONS);

                    $insert = [];
                    foreach ($userIds as $userId) {
                        $insert[] = [$newPermissionId, $userId];
                    }

                    $this->batchInsert(Table::USERPERMISSIONS_USERS, ['permissionId', 'userId'], $insert);
                }
            }
        }

        // Migrate project config for user groups
        $projectConfig = Craft::$app->getProjectConfig();

        foreach ($projectConfig->get('users.groups') ?? [] as $uid => $group) {
            $groupPermissions = array_flip($group['permissions'] ?? []);
            $save = false;

            foreach ($map as $oldPermission => $newPermissions) {
                if (isset($groupPermissions[$oldPermission])) {
                    foreach ($newPermissions as $newPermission) {
                        $groupPermissions[$newPermission] = true;
                    }
                    $save = true;
                }
            }

            if ($save) {
                $projectConfig->set("users.groups.$uid.permissions", array_keys($groupPermissions));
            }
        }

        // Clean up old permission name rows
        foreach (array_keys($map) as $oldPermission) {
            $this->delete(Table::USERPERMISSIONS, ['name' => $oldPermission]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Permission migrations are not reversible
        return true;
    }
}
