<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Commerce\Catalog\Products;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Stores;

class SiteSavedListener
{
    public function handle(SiteSaved $event): void
    {
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        app(ProductTypes::class)->afterSaveSiteHandler($event);
        app(Products::class)->afterSaveSiteHandler($event);
        app(Stores::class)->afterSaveCraftSiteHandler($event);
    }
}
