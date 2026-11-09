<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CommandPalette;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\CommandPalette\CommandPaletteItem;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * The Commerce actions the command palette offers.
 */
class CommerceActions
{
    use DescribesStores;

    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly ProductTypes $productTypes,
        private readonly Stores $stores,
    ) {
    }

    /** @return list<CommandPaletteItem> */
    public function all(CraftUser $user): array
    {
        if (!$user->can('accessPlugin-commerce')) {
            return [];
        }

        $stores = $this->stores->getAllStores();

        return [
            ...$this->orderActions($user, $stores->all()),
            ...$this->productActions(),
            ...$this->inventoryActions($user),
            ...$this->storeManagementActions($user, $stores->all()),
            ...($user->isAdmin() && $this->generalConfig->allowAdminChanges ? $this->settingsActions($stores->all()) : []),
        ];
    }

    /**
     * @param  list<Store>  $stores
     * @return list<CommandPaletteItem>
     */
    private function orderActions(CraftUser $user, array $stores): array
    {
        if (!$user->can('commerce-manageOrders')) {
            return [];
        }

        return array_map(fn(Store $store) => $this->create(
            "commerce-create-order-$store->handle",
            t('Create order', category: 'commerce'),
            $this->storeDescription(t('Orders', category: 'commerce'), $store, count($stores)),
            "commerce/orders/$store->handle/create",
        ), $stores);
    }

    /** @return list<CommandPaletteItem> */
    private function productActions(): array
    {
        return array_map(fn($productType) => $this->create(
            "commerce-create-product-$productType->handle",
            t('Create {productType} product', ['productType' => t($productType->name ?? '', category: 'site')], category: 'commerce'),
            t('Products', category: 'commerce'),
            "commerce/products/$productType->handle/new",
        ), $this->productTypes->getCreatableProductTypes());
    }

    /** @return list<CommandPaletteItem> */
    private function inventoryActions(CraftUser $user): array
    {
        if (!$user->can('commerce-manageInventoryLocations')) {
            return [];
        }

        return [
            $this->create(
                'commerce-create-inventory-location',
                t('Create inventory location', category: 'commerce'),
                t('Inventory', category: 'commerce'),
                'commerce/inventory-locations/new',
            ),
        ];
    }

    /**
     * @param  list<Store>  $stores
     * @return list<CommandPaletteItem>
     */
    private function storeManagementActions(CraftUser $user, array $stores): array
    {
        if (!$user->can('commerce-manageStoreSettings')) {
            return [];
        }

        $creatable = [
            'commerce-manageShipping' => [
                ['shippingmethods', t('Create shipping method', category: 'commerce')],
                ['shippingzones', t('Create shipping zone', category: 'commerce')],
                ['shippingcategories', t('Create shipping category', category: 'commerce')],
            ],
            'commerce-manageTaxes' => [
                ['taxrates', t('Create tax rate', category: 'commerce')],
                ['taxzones', t('Create tax zone', category: 'commerce')],
                ['taxcategories', t('Create tax category', category: 'commerce')],
            ],
            'commerce-createSales' => [
                ['sales', t('Create sale', category: 'commerce')],
            ],
            'commerce-createDiscounts' => [
                ['discounts', t('Create discount', category: 'commerce')],
            ],
            'commerce-createCatalogPricingRules' => [
                ['pricing-rules', t('Create catalog pricing rule', category: 'commerce')],
            ],
            'commerce-managePaymentCurrencies' => [
                ['payment-currencies', t('Create payment currency', category: 'commerce')],
            ],
        ];

        $actions = [];

        foreach ($stores as $store) {
            foreach ($creatable as $permission => $items) {
                if (!$user->can($permission)) {
                    continue;
                }

                foreach ($items as [$path, $label]) {
                    $actions[] = $this->create(
                        "commerce-create-$store->handle-$path",
                        $label,
                        $this->storeDescription(t('Store Management', category: 'commerce'), $store, count($stores)),
                        "commerce/store-management/$store->handle/$path/new",
                    )->searchOnly();
                }
            }
        }

        return $actions;
    }

    /**
     * @param  list<Store>  $stores
     * @return list<CommandPaletteItem>
     */
    private function settingsActions(array $stores): array
    {
        $description = t('Commerce Settings', category: 'commerce');

        $actions = [
            $this->create('commerce-create-store', t('Create store', category: 'commerce'), $description, 'commerce/settings/stores/new'),
            $this->create('commerce-create-product-type', t('Create product type', category: 'commerce'), $description, 'commerce/settings/producttypes/new'),
            $this->create('commerce-create-gateway', t('Create gateway', category: 'commerce'), $description, 'commerce/settings/gateways/new'),
        ];

        foreach ($stores as $store) {
            $storeDescription = $this->storeDescription($description, $store, count($stores));

            foreach ([
                ['order-status', t('Create order status', category: 'commerce'), "commerce/settings/orders/statuses/$store->handle/new"],
                ['line-item-status', t('Create line item status', category: 'commerce'), "commerce/settings/orders/line-item-statuses/$store->handle/new"],
                ['email', t('Create email', category: 'commerce'), "commerce/settings/emails/$store->handle/new"],
                ['pdf', t('Create PDF', category: 'commerce'), "commerce/settings/pdfs/$store->handle/new"],
            ] as [$id, $label, $path]) {
                $actions[] = $this->create("commerce-create-$store->handle-$id", $label, $storeDescription, $path);
            }
        }

        return array_map(fn(CommandPaletteItem $action) => $action->searchOnly(), $actions);
    }

    private function create(string $id, string $label, string $description, string $path): CommandPaletteItem
    {
        return new CommandPaletteItem()
            ->id($id)
            ->label($label)
            ->description($description)
            ->icon('plus')
            ->href(cp_url($path));
    }
}
