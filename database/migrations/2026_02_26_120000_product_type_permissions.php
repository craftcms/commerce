<?php

use CraftCms\Cms\Database\Migration;
use CraftCms\Commerce\Database\Table as CommerceTable;
use CraftCms\Cms\Database\Table;
use Illuminate\Support\Facades\DB;

/**
 * Migrates product type permissions from the old naming scheme to the new one:
 * - commerce-editProductType:{uid} → commerce-viewProductType:{uid} + commerce-saveProductType:{uid}
 * - commerce-createProducts:{uid} → commerce-createProductType:{uid}
 * - commerce-deleteProducts:{uid} → commerce-deleteProductType:{uid}
 */
return new class extends Migration {
    public function up(): void
    {
        $productTypeUids = DB::table(CommerceTable::PRODUCTTYPES)->pluck('uid');

        // Build the permission mapping: oldPermission => [newPermission, ...]
        $map = [];
        foreach ($productTypeUids as $uid) {
            $map[strtolower("commerce-editProductType:$uid")] = [
                strtolower("commerce-viewProductType:$uid"),
                strtolower("commerce-saveProductType:$uid"),
            ];
            $map[strtolower("commerce-createProducts:$uid")] = [
                strtolower("commerce-createProductType:$uid"),
            ];
            $map[strtolower("commerce-deleteProducts:$uid")] = [
                strtolower("commerce-deleteProductType:$uid"),
            ];
        }

        // Migrate user permissions in the database
        foreach ($map as $oldPermission => $newPermissions) {
            $userIds = DB::table(Table::USERPERMISSIONS_USERS . ' as upu')
                ->join(Table::USERPERMISSIONS . ' as up', 'up.id', '=', 'upu.permissionId')
                ->where('up.name', $oldPermission)
                ->pluck('upu.userId')
                ->unique()
                ->values();

            if ($userIds->isEmpty()) {
                continue;
            }

            foreach ($newPermissions as $newPermission) {
                // Delete the permission if it already exists
                DB::table(Table::USERPERMISSIONS)->where('name', $newPermission)->delete();

                $newPermissionId = DB::table(Table::USERPERMISSIONS)->insertGetId(['name' => $newPermission]);

                DB::table(Table::USERPERMISSIONS_USERS)->insert(
                    $userIds->map(fn($userId) => ['permissionId' => $newPermissionId, 'userId' => $userId])->all()
                );
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
    }

    public function down(): void
    {
        // Permission migrations are not reversible
    }
};
