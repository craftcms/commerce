<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Override;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

trait HasCpNavigation
{
    /**
     * The CP nav's `craft-icon` component resolves `icon` to a published icon
     * *name* (e.g. `/vendor/craft/icons/solid/cart-shopping.svg`), not a
     * filesystem path, so the base `cpNavIconPath()` (which points at
     * `resources/icon-mask.svg`) never renders here. Use a published system
     * icon until plugin-contributed custom icons are supported.
     */
    #[Override]
    protected function cpNavIconPath(): ?string
    {
        return 'cart-shopping';
    }

    #[Override]
    public function getCpNavItem(): NavItem|array|null
    {
        $item = parent::getCpNavItem();

        if (!$item instanceof NavItem) {
            return $item;
        }

        $item->group(true);
        $item->label(t('Commerce', category: 'commerce'));

        if (currentUser()?->can('commerce-manageOrders')) {
            $item->add(new NavItem()
                ->icon('receipt')
                ->label(t('Orders', category: 'commerce'))
                ->url('commerce/orders'));
        }

        if (app(ProductTypes::class)->getViewableProductTypeIds(true)) {
            $item->add(new NavItem()
                ->icon('cart-shopping')
                ->label(t('Products', category: 'commerce'))
                ->url('commerce/products'));
        }

        if (currentUser()?->can('commerce-manageDonationSettings')) {
            $item->add(new NavItem()
                ->icon('circle-dollar-to-slot')
                ->label(t('Donations', category: 'commerce'))
                ->url('commerce/donations'));
        }

        $inventoryItems = [];

        if (currentUser()?->can('commerce-manageInventoryStockLevels')) {
            $inventoryItems[] = new NavItem()
                ->label(t('Manage Inventory', category: 'commerce'))
                ->url('commerce/inventory');
        }

        if (currentUser()?->can('commerce-manageInventoryLocations')) {
            $inventoryItems[] = new NavItem()
                ->label(t('Inventory Locations', category: 'commerce'))
                ->url('commerce/inventory-locations');
        }

        $multipleLocations = app(InventoryLocations::class)->getAllInventoryLocations()->count() > 1;
        if ($multipleLocations && currentUser()?->can('commerce-manageInventoryTransfers')) {
            $inventoryItems[] = new NavItem()
                ->label(t('Inventory Transfers', category: 'commerce'))
                ->url('commerce/inventory/transfers');
        }

        if ($inventoryItems) {
            $item->add(new NavItem()->label(t('Inventory', category: 'commerce'))
                ->icon('warehouse')
                ->group(true)->subnav($inventoryItems));
        }

        if (currentUser()?->can('commerce-manageStoreSettings')) {
            $item->add(new NavItem()
                ->icon('gear')
                ->label(t('Settings', category: 'app'))
                ->group(true)->subnav([
                new NavItem()->label(t('Store Management', category: 'commerce'))->url('commerce/store-management'),
            ]));
        }

        return $item;
    }
}
