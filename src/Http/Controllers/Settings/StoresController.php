<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\db\Query;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Store\Data\Store;

use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class StoresController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function editStore(?int $storeId = null): string
    {
        $storesService = app(Stores::class);

        $brandNewStore = false;
        $allowCurrencyChange = false;

        if ($storeId !== null) {
            $storeModel = $storesService->getStoreById($storeId);
            abort_if($storeModel === null, 404, 'Store not found');

            $title = trim((string)$storeModel->getName()) ?: t('Edit Store');
        } else {
            $storeModel = new Store();
            $brandNewStore = true;
            $allowCurrencyChange = true;

            $title = t('Create a new Store');
        }

        $crumbs = [
            ['label' => t('Commerce', category: 'commerce'), 'url' => Url::url('commerce')],
            ['label' => t('Settings', category: 'commerce'), 'url' => Url::url('commerce/settings')],
            ['label' => t('Stores'), 'url' => Url::url('commerce/settings/stores')],
        ];

        $hasOrders = $storeModel->id && Order::find()
                ->trashed(null)
                ->storeId($storeModel->id)
                ->exists();

        if (!$hasOrders) {
            $allowCurrencyChange = true;
        }

        $availableSiteOptions = collect(Sites::getAllSites())->map(function($site) {
            $availableForAssignmentToNewStores = app(Stores::class)->getSiteIdsAvailableForAssignmentToNewStores();
            return [
                'label' => $site->name,
                'value' => $site->id,
                'disabled' => collect($availableForAssignmentToNewStores)->contains($site->id) === false,
            ];
        })->all();

        $currencyOptions = app(Currencies::class)->getAllCurrenciesList();

        return pageTemplate('commerce/settings/stores/_edit', [
            'brandNewStore' => $brandNewStore,
            'allowCurrencyChange' => $allowCurrencyChange,
            'title' => $title,
            'crumbs' => $crumbs,
            'store' => $storeModel,
            'currencyOptions' => $currencyOptions,
            'availableSiteOptions' => $availableSiteOptions,
            'freeOrderPaymentStrategyOptions' => $storeModel->getFreeOrderPaymentStrategyOptions(),
            'minimumTotalPriceStrategyOptions' => $storeModel->getMinimumTotalPriceStrategyOptions(),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function saveStore(Request $request): Response
    {
        $storesService = app(Stores::class);
        $storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;

        if ($storeId) {
            $store = $storesService->getStoreById($storeId);
            abort_if($store === null, 400, "Invalid store ID: $storeId");
        } else {
            $store = new Store();
        }

        $store->setName($request->input('name'));
        $store->handle = $request->input('handle');
        $store->setAutoSetNewCartAddresses($request->input('autoSetNewCartAddresses'));
        $store->setAutoSetCartShippingMethodOption($request->input('autoSetCartShippingMethodOption'));
        $store->setAutoSetPaymentSource($request->input('autoSetPaymentSource'));
        $store->setAllowEmptyCartOnCheckout($request->input('allowEmptyCartOnCheckout'));
        $store->setAllowCheckoutWithoutPayment($request->input('allowCheckoutWithoutPayment'));
        $store->setAllowPartialPaymentOnCheckout($request->input('allowPartialPaymentOnCheckout'));
        $store->setRequireShippingAddressAtCheckout($request->input('requireShippingAddressAtCheckout'));
        $store->setRequireBillingAddressAtCheckout($request->input('requireBillingAddressAtCheckout'));
        $store->setRequireShippingMethodSelectionAtCheckout($request->input('requireShippingMethodSelectionAtCheckout'));
        $store->setUseBillingAddressForTax($request->input('useBillingAddressForTax'));
        $store->setValidateOrganizationTaxIdAsVatId($request->input('validateOrganizationTaxIdAsVatId'));
        $store->setOrderReferenceFormat($request->input('orderReferenceFormat'));
        $store->setFreeOrderPaymentStrategy($request->input('freeOrderPaymentStrategy'));
        $store->setMinimumTotalPriceStrategy($request->input('minimumTotalPriceStrategy'));
        $store->primary = (bool)$request->input('primary', $store->primary);

        if ($currency = $request->input('currency')) {
            $store->setCurrency($currency);
        }

        if ($storeId && $savedStore = $storesService->getStoreById($storeId)) {
            $store->uid = $savedStore->uid;
            $store->sortOrder = $savedStore->sortOrder;
        } elseif (!$storeId) {
            $store->sortOrder = new Query()->from(Table::STORES)->max('[[sortOrder]]') + 1;
        }

        if (!$store->validate() || !$storesService->saveStore($store)) {
            return $this->asModelFailure($store, t('Couldn\'t save the store.'), 'store');
        }

        if ($siteId = $request->input('siteId')) {
            $siteStore = collect($storesService->getAllSiteStores())->where('siteId', $siteId)->first();
            $siteStore->storeId = $store->id;
            $storesService->saveSiteStore($siteStore);
        }

        return $this->asModelSuccess($store, t('Store saved.'), 'store');
    }

    public function storesIndex(): string
    {
        $stores = app(Stores::class)->getAllStores();

        $crumbs = [
            ['label' => t('Commerce', category: 'commerce'), 'url' => Url::url('commerce')],
        ];

        $menuItems = [];
        $stores->each(function(Store $s) use (&$menuItems) {
            $m = [];
            $m[] = ['label' => t('Payment Currencies', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/payment-currencies')];
            $m[] = ['label' => t('Discounts', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/discounts')];

            if (app(CatalogPricingRules::class)->canUseCatalogPricingRules()) {
                $m[] = ['label' => t('Pricing Rules', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/pricing-rules')];
            } else {
                $m[] = ['label' => t('Sales', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/sales')];
            }

            $m[] = ['label' => t('Shipping Methods', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/shippingmethods')];
            $m[] = ['label' => t('Shipping Zones', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/shippingzones')];
            $m[] = ['label' => t('Shipping Categories', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/shippingcategories')];
            $m[] = ['label' => t('Tax Rates', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/taxrates')];
            $m[] = ['label' => t('Tax Zones', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/taxzones')];
            $m[] = ['label' => t('Tax Categories', category: 'commerce'), 'url' => Url::cpUrl('commerce/store-management/' . $s->handle . '/taxcategories')];

            $menuItems[$s->handle] = $m;
        });

        return pageTemplate('commerce/settings/stores/index', [
            'stores' => $stores,
            'crumbs' => $crumbs,
            'sitesStores' => app(Stores::class)->getAllSiteStores(),
            'primaryStoreId' => app(Stores::class)->getPrimaryStore()->id,
            'menuItems' => $menuItems,
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function deleteStore(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $siteId = $request->input('id');
        abort_if(!$siteId, 400, 'Missing store id');

        app(Stores::class)->deleteStoreById($siteId);

        return $this->asSuccess();
    }

    public function reorderStores(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));

        if (!app(Stores::class)->reorderStores($ids)) {
            return $this->asFailure(t('Couldn\'t reorder stores.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function editSiteStores(): string
    {
        $crumbs = [
            ['label' => t('Commerce', category: 'commerce'), 'url' => Url::url('commerce')],
        ];

        return pageTemplate('commerce/settings/stores/_siteStore', [
            'crumbs' => $crumbs,
            'stores' => app(Stores::class)->getAllStores(),
            'sites' => Sites::getAllSites(),
            'sitesStores' => app(Stores::class)->getAllSiteStores(),
            'primaryStoreId' => app(Stores::class)->getPrimaryStore()->id,
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function saveSiteStores(Request $request): Response
    {
        $siteStoresData = $request->input('siteStores', []);
        $sitesStores = app(Stores::class)->getAllSiteStores();
        $stores = app(Stores::class)->getAllStores();

        foreach ($sitesStores as $siteStore) {
            if (isset($siteStoresData[$siteStore->siteId])) {
                $siteStore->storeId = $siteStoresData[$siteStore->siteId]['storeId'];
            }
        }

        $unassignedStores = [];
        foreach ($stores as $store) {
            $storeAssigned = false;
            foreach ($sitesStores as $siteStore) {
                if ($siteStore->storeId == $store->id) {
                    $storeAssigned = true;
                }
            }
            if (!$storeAssigned) {
                $unassignedStores[] = $store->getName();
            }
        }
        if ($unassignedStores) {
            return $this->asFailure(
                t('{storeNames} {num, plural, =1{has} other{have}} not been assigned to a site.', [
                    'storeNames' => implode(', ', $unassignedStores),
                    'num' => count($unassignedStores),
                ], category: 'commerce'),
                data: ['sitesStores' => collect($sitesStores)]
            );
        }

        foreach ($sitesStores as $siteStore) {
            app(Stores::class)->saveSiteStore($siteStore);
        }

        return $this->asSuccess(t('Site store mapping saved.', category: 'commerce'));
    }
}
