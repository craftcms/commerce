<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Concerns;

use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Support\ObjectState;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Resolves the store a store-management screen is for, and checks the current user can manage it.
 *
 * Every store-management controller resolves its own `Store` from a `storeHandle` route segment,
 * since there's no route binding for handle-scoped resources, so `resolveStore()` takes the handle
 * per action rather than being injected.
 */
trait HasStoreManagementScreen
{
    protected function resolveStore(?string $storeHandle): Store
    {
        if ($storeHandle === null || !$store = app(Stores::class)->getStoreByHandle($storeHandle)) {
            $store = app(Stores::class)->getPrimaryStore();
        }

        return $store;
    }

    protected function requireStoreAccess(?int $storeId): void
    {
        if (!ObjectState::has($this, 'allowableStoreIds')) {
            ObjectState::set($this, 'allowableStoreIds', app(Stores::class)->getStoresByUserId(currentUser()?->getCraftUserId())->map(fn(Store $s) => $s->id)->all());
        }

        $allowableStoreIds = ObjectState::get($this, 'allowableStoreIds');

        abort_unless($storeId !== null && in_array($storeId, $allowableStoreIds, true), 403, t('You are not permitted to perform this action for this store.', category: 'commerce'));
    }
}
