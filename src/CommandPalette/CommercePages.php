<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CommandPalette;

use CraftCms\Cms\Cp\CommandPalette\CommandPaletteItem;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * The Commerce pages the command palette offers, beyond those in the Commerce nav.
 */
class CommercePages
{
    public function __construct(
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

        return [
            ...$this->productTypePages(),
            ...$this->catalogPricingPages($user),
            ...$this->storeManagementPages($user),
            ...($user->isAdmin() ? $this->settingsPages() : []),
        ];
    }

    /** @return list<CommandPaletteItem> */
    private function productTypePages(): array
    {
        $viewableIds = $this->productTypes->getViewableProductTypeIds(true);
        $pages = [];

        foreach ($this->productTypes->getAllProductTypes() as $productType) {
            if (!in_array($productType->id, $viewableIds)) {
                continue;
            }

            $pages[] = $this->page(
                "commerce-product-type-$productType->handle",
                t($productType->name ?? '', category: 'site'),
                t('Products', category: 'commerce'),
                'cart-shopping',
                "commerce/products/$productType->handle",
            );
        }

        return $pages;
    }

    /** @return list<CommandPaletteItem> */
    private function catalogPricingPages(CraftUser $user): array
    {
        if (!$user->can('commerce-manageStoreSettings') || !$user->can('commerce-managePromotions')) {
            return [];
        }

        return [
            $this->page(
                'commerce-catalog-pricing',
                t('Catalog Pricing', category: 'commerce'),
                t('Commerce', category: 'commerce'),
                'tags',
                'commerce/catalog-pricing',
            ),
        ];
    }

    /** @return list<CommandPaletteItem> */
    private function storeManagementPages(CraftUser $user): array
    {
        if (!$user->can('commerce-manageStoreSettings')) {
            return [];
        }

        $stores = $this->stores->getAllStores();
        $pages = [];

        foreach ($stores as $store) {
            $areas = [
                'commerce-manageShipping' => [
                    ['shippingmethods', t('Shipping Methods', category: 'commerce'), 'truck'],
                    ['shippingzones', t('Shipping Zones', category: 'commerce'), 'map'],
                    ['shippingcategories', t('Shipping Categories', category: 'commerce'), 'boxes-stacked'],
                ],
                'commerce-manageTaxes' => [
                    ['taxrates', t('Tax Rates', category: 'commerce'), 'percent'],
                    ['taxzones', t('Tax Zones', category: 'commerce'), 'map'],
                    ['taxcategories', t('Tax Categories', category: 'commerce'), 'boxes-stacked'],
                ],
                'commerce-managePromotions' => [
                    ['sales', t('Sales', category: 'commerce'), 'tag'],
                    ['discounts', t('Discounts', category: 'commerce'), 'percent'],
                    ['pricing-rules', t('Catalog Pricing Rules', category: 'commerce'), 'tags'],
                ],
                'commerce-managePaymentCurrencies' => [
                    ['payment-currencies', t('Payment Currencies', category: 'commerce'), 'coins'],
                ],
            ];

            foreach ($areas as $permission => $areaPages) {
                if (!$user->can($permission)) {
                    continue;
                }

                foreach ($areaPages as [$path, $label, $icon]) {
                    $pages[] = $this->page(
                        "commerce-$store->handle-$path",
                        $label,
                        $this->storeDescription($store, $stores->count()),
                        $icon,
                        "commerce/store-management/$store->handle/$path",
                    );
                }
            }
        }

        return $pages;
    }

    /** @return list<CommandPaletteItem> */
    private function settingsPages(): array
    {
        $settings = [
            ['general', t('General', category: 'commerce'), 'gear'],
            ['stores', t('Stores', category: 'commerce'), 'store'],
            ['producttypes', t('Product Types', category: 'commerce'), 'boxes-stacked'],
            ['orders', t('Orders', category: 'commerce'), 'receipt'],
            ['orders/statuses', t('Order Statuses', category: 'commerce'), 'receipt'],
            ['orders/line-item-statuses', t('Line Item Statuses', category: 'commerce'), 'receipt'],
            ['pdfs', t('PDFs', category: 'commerce'), 'file-pdf'],
            ['emails', t('Emails', category: 'commerce'), 'envelopes'],
            ['gateways', t('Gateways', category: 'commerce'), 'credit-card'],
            ['transfers', t('Transfers', category: 'commerce'), 'truck-arrow-right'],
        ];

        return array_map(fn(array $setting) => $this->page(
            'commerce-settings-' . str_replace('/', '-', $setting[0]),
            $setting[1],
            t('Commerce Settings', category: 'commerce'),
            $setting[2],
            "commerce/settings/$setting[0]",
        ), $settings);
    }

    private function storeDescription(Store $store, int $storeCount): string
    {
        $description = t('Store Management', category: 'commerce');

        return $storeCount > 1 ? sprintf('%s → %s', $description, t($store->name ?? '', category: 'site')) : $description;
    }

    private function page(string $id, string $label, string $description, string $icon, string $path): CommandPaletteItem
    {
        return new CommandPaletteItem()
            ->id($id)
            ->label($label)
            ->description($description)
            ->icon($icon)
            ->href(cp_url($path));
    }
}
