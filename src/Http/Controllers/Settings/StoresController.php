<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\db\Query;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table as DbTable;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Data\Store;

use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

class StoresController extends BaseSettingsController
{
    protected function crumbs(?string $title = null, ?string $url = null): array
    {
        $crumbs = parent::crumbs(t('Stores'), cp_url('commerce/settings/stores'));

        if ($title || $url) {
            $crumbs[] = ['label' => $title, 'href' => $url];
        }

        return $crumbs;
    }

    public function editStore(?int $storeId = null): CpScreenResponse
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

        $form = $this->buildStoreForm($storeModel, $brandNewStore, $allowCurrencyChange, $availableSiteOptions, $currencyOptions);
        $values = $this->storeInitialValues($storeModel);

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs($brandNewStore ? null : $title))
            ->redirectUrl('commerce/settings/stores')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve($form, new FormContext(
                    values: $values,
                    mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
                )),
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'saveStore']),
                ],
            ]);
    }

    /**
     * @param  list<array{label: string, value: mixed, disabled?: bool}>  $availableSiteOptions
     * @param  list<array{label: string, value: mixed}>  $currencyOptions
     */
    private function buildStoreForm(
        Store $storeModel,
        bool $brandNewStore,
        bool $allowCurrencyChange,
        array $availableSiteOptions,
        array $currencyOptions,
    ): Form {
        $currencyControl = Choice::make('currency')->options($currencyOptions);

        if (!$allowCurrencyChange) {
            $currencyControl->mode(ControlMode::Disabled);
        }

        $currencyField = Field::make(t('Currency', category: 'commerce'), $currencyControl)->required();

        if (!$allowCurrencyChange) {
            $currencyField->tip(t('The primary currency cannot be changed after orders are placed.', category: 'commerce'));
        }

        $handle = Handle::make('handle');

        if ($brandNewStore) {
            $handle->source('name');
        }

        $storeFields = [
            $brandNewStore ? null : HiddenField::make('storeId'),
            Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                ->required(),
            Field::make(t('Handle', category: 'app'), $handle)
                ->instructions(t('How you’ll refer to this store in the templates.', category: 'app'))
                ->required(),
            $brandNewStore
                ? Field::make(t('Sites', category: 'commerce'), Choice::make('siteId')->options($availableSiteOptions))
                    ->instructions(t('Every new store must be assigned to at least one site.', category: 'commerce'))
                : null,
            $currencyField,
            $storeModel->primary
                ? null
                : Field::make(t('Make this the primary store', category: 'commerce'), Lightswitch::make('primary')),
        ];

        $settingsFields = [
            Field::make(t('Auto Set New Cart Addresses', category: 'commerce'), Lightswitch::make('autoSetNewCartAddresses'))
                ->instructions(t('Whether the user’s primary shipping and billing addresses should be set automatically on new carts.', category: 'commerce')),
            Field::make(t('Auto Set Cart Shipping Method Option', category: 'commerce'), Lightswitch::make('autoSetCartShippingMethodOption'))
                ->instructions(t('Whether the first available shipping method option should be set automatically on carts.', category: 'commerce')),
            Field::make(t('Auto Set Payment Source', category: 'commerce'), Lightswitch::make('autoSetPaymentSource'))
                ->instructions(t('Whether the user’s primary payment source should be set automatically on new carts.', category: 'commerce')),
            Field::make(t('Allow Empty Cart On Checkout', category: 'commerce'), Lightswitch::make('allowEmptyCartOnCheckout')),
            Field::make(t('Allow Checkout Without Payment', category: 'commerce'), Lightswitch::make('allowCheckoutWithoutPayment')),
            Field::make(t('Allow Partial Payment On Checkout', category: 'commerce'), Lightswitch::make('allowPartialPaymentOnCheckout')),
            Field::make(t('Free Order Payment Strategy', category: 'commerce'), Choice::make('freeOrderPaymentStrategy')
                ->options(self::choiceOptions($storeModel->getFreeOrderPaymentStrategyOptions())))
                ->instructions(t('Strategy to apply when an order is free or has a zero balance.', category: 'commerce'))
                ->required(),
            Field::make(t('Minimum Total Price Strategy', category: 'commerce'), Choice::make('minimumTotalPriceStrategy')
                ->options(self::choiceOptions($storeModel->getMinimumTotalPriceStrategyOptions())))
                ->instructions(t('Strategy to apply when calculating the minimum order price.', category: 'commerce'))
                ->required(),
            Field::make(t('Require Shipping Address At Checkout', category: 'commerce'), Lightswitch::make('requireShippingAddressAtCheckout')),
            Field::make(t('Require Billing Address At Checkout', category: 'commerce'), Lightswitch::make('requireBillingAddressAtCheckout')),
            Field::make(t('Require Shipping Method Selection At Checkout', category: 'commerce'), Lightswitch::make('requireShippingMethodSelectionAtCheckout')),
            Field::make(t('Use Billing Address For Tax', category: 'commerce'), Lightswitch::make('useBillingAddressForTax')),
            Field::make(t('Validate Business Tax ID as Vat ID', category: 'commerce'), Lightswitch::make('validateOrganizationTaxIdAsVatId')),
            Field::make(t('Order Reference Number Format', category: 'commerce'), Text::make('orderReferenceFormat')->monospace())
                ->instructions(t('A friendly reference number will be generated based on this format when a cart is completed and becomes an order. For example {ex1}, or {ex2}. The result of this format must be unique.', [
                    'ex1' => '2018-{number[:7]}',
                    'ex2' => "{{object.dateCompleted|date('y')}}-{{ seq(object.dateCompleted|date('y'), 8) }}",
                ], category: 'commerce')),
        ];

        return Form::make()
            ->addTab(t('Store', category: 'commerce'), array_values(array_filter($storeFields)))
            ->addTab(t('Settings', category: 'commerce'), $settingsFields);
    }

    /** @return array<string, mixed> */
    private function storeInitialValues(Store $storeModel): array
    {
        return [
            'storeId' => $storeModel->id,
            'name' => $storeModel->getName(false),
            'handle' => $storeModel->handle,
            'siteId' => null,
            'currency' => $storeModel->getCurrency()?->getCode(),
            'primary' => $storeModel->primary,
            'autoSetNewCartAddresses' => $storeModel->getAutoSetNewCartAddresses(),
            'autoSetCartShippingMethodOption' => $storeModel->getAutoSetCartShippingMethodOption(),
            'autoSetPaymentSource' => $storeModel->getAutoSetPaymentSource(),
            'allowEmptyCartOnCheckout' => $storeModel->getAllowEmptyCartOnCheckout(),
            'allowCheckoutWithoutPayment' => $storeModel->getAllowCheckoutWithoutPayment(),
            'allowPartialPaymentOnCheckout' => $storeModel->getAllowPartialPaymentOnCheckout(),
            'freeOrderPaymentStrategy' => $storeModel->getFreeOrderPaymentStrategy(),
            'minimumTotalPriceStrategy' => $storeModel->getMinimumTotalPriceStrategy(),
            'requireShippingAddressAtCheckout' => $storeModel->getRequireShippingAddressAtCheckout(),
            'requireBillingAddressAtCheckout' => $storeModel->getRequireBillingAddressAtCheckout(),
            'requireShippingMethodSelectionAtCheckout' => $storeModel->getRequireShippingMethodSelectionAtCheckout(),
            'useBillingAddressForTax' => $storeModel->getUseBillingAddressForTax(),
            'validateOrganizationTaxIdAsVatId' => $storeModel->getValidateOrganizationTaxIdAsVatId(),
            'orderReferenceFormat' => $storeModel->getOrderReferenceFormat(),
        ];
    }

    /**
     * @param  array<string, string>  $options  Value-keyed label map, as returned by e.g.
     *   {@see Store::getFreeOrderPaymentStrategyOptions()}.
     * @return list<array{value: string, label: string}>
     */
    private static function choiceOptions(array $options): array
    {
        return array_map(
            fn(string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($options),
            $options,
        );
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
            $store->sortOrder = new Query()->from(DbTable::STORES)->max('[[sortOrder]]') + 1;
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

    public function storesIndex(): CpScreenResponse
    {
        $stores = app(Stores::class)->getAllStores();

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

        $rows = $stores->map(fn(Store $s) => [
            'id' => $s->id,
            'name' => [
                'label' => t($s->getName(), category: 'site'),
                'url' => Url::cpUrl('commerce/settings/stores/' . $s->id),
            ],
            'handle' => $s->handle,
            'sites' => $s->getSiteNames()->join(', '),
            'currency' => $s->getCurrency()?->getCode() ?? '',
            'primary' => $s->primary ? t('Yes') : '',
            'management' => [
                'label' => t('Store Management', category: 'commerce'),
                'items' => $menuItems[$s->handle],
            ],
            '_deletable' => !$s->primary,
        ])->all();

        $title = t('Stores');

        $showNewStoreButton = !$this->readOnly && $stores->count() < count(Sites::getAllSites());

        if ($showNewStoreButton) {
            $showNewStoreButton = (Plugin::getInstance()->is(Plugin::EDITION_PRO, '=')
                    && $stores->count() < Plugin::EDITION_PRO_STORE_LIMIT
                    && app(CatalogPricingRules::class)->canUseCatalogPricingRules())
                || (Plugin::getInstance()->is(Plugin::EDITION_ENTERPRISE, '=')
                    && app(CatalogPricingRules::class)->canUseCatalogPricingRules());
        }

        $form = Form::make([
            Table::make('stores')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'sites', 'label' => t('Sites', category: 'commerce')],
                    ['key' => 'currency', 'label' => t('Currency', category: 'commerce')],
                    ['key' => 'primary', 'label' => t('Primary', category: 'commerce')],
                    ['key' => 'management', 'label' => t('Store Management', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No stores exist yet.', category: 'commerce'))
                ->createAction(
                    $showNewStoreButton ? t('New store') : null,
                    $showNewStoreButton ? Url::cpUrl('commerce/settings/stores/new') : null,
                )
                ->when(!$this->readOnly, fn(Table $table) => $table
                    ->reorderable(action([self::class, 'reorderStores']))
                    ->deletable(
                        action([self::class, 'deleteStore']),
                        t('Are you sure you want to permanently delete this store and everything in it?', category: 'commerce'),
                    )),
        ]);

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve($form, new FormContext()),
            ]);
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
