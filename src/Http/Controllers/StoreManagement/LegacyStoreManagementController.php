<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Commerce\Store\Data\Store;

/**
 * Temporary home for every store-management controller not yet converted to the Form system.
 *
 * {@see BaseStoreManagementController::getSectionCrumb()} is a real abstract method — a
 * not-yet-converted controller has no Form-system crumb logic to provide, so it extends this
 * class instead, which stubs that one method just enough to satisfy the parent. Converting a
 * controller is then a one-line change: switch its `extends` from this class to
 * {@see BaseStoreManagementController} directly, and implement `getSectionCrumb()` for real.
 *
 * Once the last controller has been converted, nothing will extend this class any more —
 * delete it at that point along with {@see \CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen},
 * whose only remaining callers will have gone with it.
 */
abstract readonly class LegacyStoreManagementController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        throw new \LogicException(static::class.' has not been converted to the Form system yet — it must not call crumbs().');
    }
}
