<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\commerce\console\Controller;
use craft\commerce\Plugin;
use craft\elements\User;
use yii\console\ExitCode;

/**
 * Creates test users with various commerce product type permission combinations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.0
 */
class CreateTestUsersController extends Controller
{
    /**
     * Creates test users for every combination of commerce product type permissions.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        if (empty($productTypes)) {
            $this->stderr("No product types found.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $elementsService = Craft::$app->getElements();
        $permissionsService = Craft::$app->getUserPermissions();

        // Permission keys and their short labels for username generation
        $permissionKeys = [
            'view' => 'commerce-viewProductType',
            'create' => 'commerce-createProductType',
            'save' => 'commerce-saveProductType',
            'delete' => 'commerce-deleteProductType',
        ];

        // Generate all combinations of create/save/delete (view is always required)
        $nestedKeys = ['create', 'save', 'delete'];
        $combos = [[]]; // start with view-only (no nested)
        foreach ($nestedKeys as $key) {
            $newCombos = [];
            foreach ($combos as $combo) {
                $newCombos[] = $combo;
                $newCombos[] = array_merge($combo, [$key]);
            }
            $combos = $newCombos;
        }

        $created = 0;

        foreach ($productTypes as $productType) {
            $suffix = ':' . $productType->uid;
            $typeHandle = $productType->handle;

            foreach ($combos as $combo) {
                // Build username from permissions
                $parts = ['view'];
                $parts = array_merge($parts, $combo);
                $username = $typeHandle . '-' . implode('-', $parts);
                $email = $username . '@test.com';

                // Check if user already exists
                $existing = User::find()->username($username)->one();
                if ($existing) {
                    $this->stdout("User '$username' already exists, skipping.\n");
                    continue;
                }

                // Create the user
                $user = new User();
                $user->username = $username;
                $user->email = $email;
                $user->active = true;
                $user->newPassword = 'password';

                if (!$elementsService->saveElement($user, false)) {
                    $this->stderr("Failed to create user '$username': " . implode(', ', $user->getFirstErrors()) . "\n");
                    continue;
                }

                // Build permission list - view is always included
                $permissions = [
                    'accesscp',
                    'accessplugin-commerce',
                    strtolower($permissionKeys['view'] . $suffix),
                ];

                foreach ($combo as $key) {
                    $permissions[] = strtolower($permissionKeys[$key] . $suffix);
                }

                $permissionsService->saveUserPermissions($user->id, $permissions);

                $this->stdout("Created user '$username' with permissions: " . implode(', ', $parts) . "\n");
                $created++;
            }
        }

        $this->stdout("\nDone. Created $created test users.\n");
        return ExitCode::OK;
    }
}
