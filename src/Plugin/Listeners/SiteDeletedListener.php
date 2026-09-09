<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Stores;

class SiteDeletedListener
{
    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function handle(SiteDeleted $event): void
    {
        if (!$this->plugin->isInstalled) {
            return;
        }

        app(Stores::class)->afterDeleteCraftSiteHandler($event);
    }
}
