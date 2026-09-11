<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;

/**
 * Shared ancestor for Commerce's store-management settings controllers (shipping, tax,
 * promotions, payment currencies, etc). All of the actual shared behavior still lives in
 * {@see HasStoreManagementScreen} — this class exists so every controller in this namespace
 * has a common type to extend, matching the `BaseSettingsController` convention next door,
 * rather than each one pulling the trait in independently.
 *
 * Every controller still resolves its own `Store` from a `storeHandle` route segment per
 * action method, not via the constructor — there's no framework-level route binding for
 * handle-scoped resources in cms-6, so `resolveStore()` stays a plain method taking
 * `$storeHandle` as a parameter rather than a constructor-injected dependency.
 */
abstract readonly class BaseStoreManagementController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;
}
