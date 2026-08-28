<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Auth\Events\ElementAuthorizing;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\StoreSettings;

class ElementAuthorizingListener
{
    public function handle(ElementAuthorizing $event): void
    {
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        match ($event->ability) {
            'view' => app(StoreSettings::class)->authorizeStoreLocationView($event),
            'save', 'createDrafts' => app(StoreSettings::class)->authorizeStoreLocationEdit($event),
            default => null,
        };

        match ($event->ability) {
            'view' => app(InventoryLocations::class)->authorizeInventoryLocationAddressView($event),
            'save', 'createDrafts' => app(InventoryLocations::class)->authorizeInventoryLocationAddressEdit($event),
            default => null,
        };
    }
}
