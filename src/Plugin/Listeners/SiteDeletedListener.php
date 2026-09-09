<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Stores;

class SiteDeletedListener
{
    public function handle(SiteDeleted $event): void
    {
        // TODO: fix in Commerce 6.0 - replace Plugin::getInstance() with proper DI (e.g. inject Plugin::class)
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        app(Stores::class)->afterDeleteCraftSiteHandler($event);
    }
}
