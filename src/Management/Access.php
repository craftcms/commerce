<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Management;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Authorization shared by user-facing Commerce integrations. */
class Access
{
    public function permission(CraftUser $actor, string ...$permissions): void
    {
        Gate::forUser($actor)->authorize('accessPlugin-commerce');

        foreach ($permissions as $permission) {
            Gate::forUser($actor)->authorize($permission);
        }
    }

    public function configuration(CraftUser $actor, bool $write = false): void
    {
        $this->permission($actor);

        if (!$actor->isAdmin() || ($write && !app(GeneralConfig::class)->allowAdminChanges)) {
            throw new AuthorizationException('You are not authorized to manage Commerce configuration.');
        }
    }

    public function store(CraftUser $actor, int $storeId): void
    {
        if (!app(Stores::class)->getStoresByUserId($actor->getCraftUserId())->contains('id', $storeId)) {
            throw new AuthorizationException('Store not found.');
        }
    }
}
