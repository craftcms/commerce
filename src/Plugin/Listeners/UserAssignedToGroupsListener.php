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
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        app(CatalogPricingRules::class)->afterSaveUserHandler($event);
    }
}
