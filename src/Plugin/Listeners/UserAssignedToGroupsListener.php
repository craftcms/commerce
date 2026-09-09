<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\User\Events\UserAssignedToGroups;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Plugin;

class UserAssignedToGroupsListener
{
    public function handle(UserAssignedToGroups $event): void
    {
        // TODO: fix in Commerce 6.0 - replace Plugin::getInstance() with proper DI (e.g. inject Plugin::class)
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        app(CatalogPricingRules::class)->afterSaveUserHandler($event);
    }
}
