<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Concerns;

use craft\commerce\Plugin;
use craft\web\assets\admintable\AdminTableAsset;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Commerce\Store\Models\Store;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Shared store-scoped CP screen chrome for Commerce's store-management settings controllers
 * (shipping, tax, promotions, payment currencies, etc). Every controller that needs it resolves
 * its own `Store` from a `storeHandle` route segment — there's no framework-level route binding
 * for handle-scoped resources in cms-6 (confirmed: every handle-scoped core controller does the
 * same manual resolve-and-404), so this stays a plain trait rather than a shared base class.
 */
trait HasStoreManagementScreen
{
    protected function resolveStore(?string $storeHandle): Store
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        return $store;
    }

    protected function storeManagementCpScreen(?string $storeHandle, bool $isIndex = true, bool $hasStoreSwitcher = true): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        $screen = new CpScreenResponse();

        $screen->crumbs(array_filter([
            ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
            $hasStoreSwitcher ? $this->getStoreSwitcher($storeHandle) : null,
        ]));

        if ($isIndex) {
            // Most index pages need the admin table asset bundle
            \Craft::$app->getView()->registerAssetBundle(AdminTableAsset::class);

            $segments = request()->segments();
            $selectedItem = count($segments) >= 4 ? $segments[3] : 'general';

            $screen->pageSidebarTemplate('commerce/_includes/_storeManagementNav', [
                'storeSettingsNav' => $this->getStoreSettingsNav(),
                'store' => $store,
                'selectedItem' => $selectedItem,
            ]);
        }

        $screen->title(t('Store Management', category: 'commerce'));
        $screen->selectedSubnavItem('store-management');

        return $screen;
    }

    protected function getStoreSwitcher(?string $storeHandle = null): array
    {
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $store = $storeHandle ? Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle) : null;

        $storeItems = $stores->filter(function(Store $s) {
            foreach ($s->getSites() as $site) {
                if (currentUser()?->can('editSite:' . $site->uid)) {
                    return true;
                }
            }

            return false;
        })->map(function(Store $s) use ($storeHandle) {
            $segments = request()->segments();
            $storeSubSection = count($segments) >= 4 ? $segments[3] : null;

            return [
                'status' => null,
                'label' => t($s->getName(), category: 'site'),
                'url' => 'commerce/store-management/' . $s->handle . ($storeSubSection ? '/' . $storeSubSection : ''),
                'selected' => $storeHandle === $s->handle,
                'attributes' => [
                    'data' => [
                        'store-handle' => $s->handle,
                    ],
                ],
            ];
        })->all();

        return [
            'id' => 'site-crumb',
            'iconAltText' => t('Store', category: 'commerce'),
            'icon' => 'store',
            'label' => $store?->getName() ?? t('Store Management', category: 'commerce'),
            'menu' => [
                'label' => t('Select site'),
                'items' => $storeItems,
            ],
        ];
    }

    protected function getStoreSettingsNav(): array
    {
        $storeSettingsNav = [];

        $storeSettingsNav['general'] = [
            'label' => t('General', category: 'commerce'),
            'path' => '',
            'disabled' => !currentUser()?->can('commerce-manageGeneralStoreSettings'),
        ];

        $storeSettingsNav['payment-currencies'] = [
            'label' => t('Payment Currencies', category: 'commerce'),
            'path' => 'payment-currencies',
            'disabled' => !currentUser()?->can('commerce-managePaymentCurrencies'),
        ];

        $managePromotions = (bool)currentUser()?->can('commerce-managePromotions');
        $storeSettingsNav['pricing-heading'] = [
            'heading' => t('Pricing', category: 'commerce'),
        ];

        $storeSettingsNav['discounts'] = [
            'label' => t('Discounts', category: 'commerce'),
            'path' => 'discounts',
            'disabled' => !$managePromotions,
        ];

        if (Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules()) {
            $storeSettingsNav['pricing-rules'] = [
                'label' => t('Pricing Rules', category: 'commerce'),
                'path' => 'pricing-rules',
                'disabled' => !$managePromotions,
            ];
        } else {
            $storeSettingsNav['sales'] = [
                'label' => t('Sales', category: 'commerce'),
                'path' => 'sales',
                'disabled' => !$managePromotions,
            ];
        }

        $storeSettingsNav['shipping-header'] = [
            'heading' => t('Shipping', category: 'commerce'),
        ];

        $manageShipping = (bool)currentUser()?->can('commerce-manageShipping');
        $storeSettingsNav['shippingmethods'] = [
            'label' => t('Shipping Methods', category: 'commerce'),
            'path' => 'shippingmethods',
            'disabled' => !$manageShipping,
        ];

        $storeSettingsNav['shippingzones'] = [
            'label' => t('Shipping Zones', category: 'commerce'),
            'path' => 'shippingzones',
            'disabled' => !$manageShipping,
        ];

        $storeSettingsNav['shippingcategories'] = [
            'label' => t('Shipping Categories', category: 'commerce'),
            'path' => 'shippingcategories',
            'disabled' => !$manageShipping,
        ];

        $storeSettingsNav['tax'] = [
            'heading' => t('Tax', category: 'commerce'),
        ];

        $manageTaxes = (bool)currentUser()?->can('commerce-manageTaxes');
        if (Plugin::getInstance()->getTaxes()->viewTaxRates()) {
            $storeSettingsNav['taxrates'] = [
                'label' => t('Tax Rates', category: 'commerce'),
                'path' => 'taxrates',
                'disabled' => !$manageTaxes,
            ];
        }

        if (Plugin::getInstance()->getTaxes()->viewTaxZones()) {
            $storeSettingsNav['taxzones'] = [
                'label' => t('Tax Zones', category: 'commerce'),
                'path' => 'taxzones',
                'disabled' => !$manageTaxes,
            ];
        }

        if (Plugin::getInstance()->getTaxes()->viewTaxCategories()) {
            $storeSettingsNav['taxcategories'] = [
                'label' => t('Tax Categories', category: 'commerce'),
                'path' => 'taxcategories',
                'disabled' => !$manageTaxes,
            ];
        }

        return $storeSettingsNav;
    }
}
