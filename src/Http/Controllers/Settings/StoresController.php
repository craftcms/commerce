<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\db\Query;
use CraftCms\Cms\Cp\Components\Select;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\Handle;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\HiddenField;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table as DbTable;
use CraftCms\Commerce\Form\Controls\SiteStores;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Data\Store;

use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class StoresController extends BaseSettingsController
{
    protected function getSectionCrumb(): array
    {
        return ['label' => t('Stores', category: 'commerce'), 'href' => cp_url('commerce/settings/stores')];
    }

    #[\Override]
    protected function subnav(): array
    {
        $path = request()->craftPath();

        return [
            new NavItem()
                ->label(t('Stores', category: 'commerce'))
                ->url(cp_url('commerce/settings/stores'))
                ->selected($path === 'commerce/settings/stores'),
            new NavItem()
                ->label(t('Sites'))
                ->url(cp_url('commerce/settings/stores/sites'))
                ->selected($path === 'commerce/settings/stores/sites'),
        ];
    }

    public function editStore(?int $storeId = null): CpScreenResponse
    {
        $storesService = app(Stores::class);

        $brandNewStore = false;
        $allowCurrencyChange = false;

        if ($storeId !== null) {
            $storeModel = $storesService->getStoreById($storeId);
            abort_if($storeModel === null, 404, 'Store not found');

            $title = trim((string)$storeModel->getName()) ?: t('Edit Store', category: 'commerce');
        } else {
            $storeModel = new Store();
            $brandNewStore = true;
            $allowCurrencyChange = true;

            $title = t('Create a new store', category: 'commerce');
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

        return $this->cpScreenResponse(subnav: false)
            ->title($title)
            ->crumbs($brandNewStore ? $this->crumbs() : $this->crumbs(['label' => $title]))
            ->redirectUrl('commerce/settings/stores')
            ->inertiaPage('Ui', [
                'ui' => $this->formResolver->resolve($form, new UiContext(
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
    ): Ui {
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
                ->instructions(t('How you’ll refer to this store in the templates.', category: 'commerce'))
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

        $booleanOptions = [
            ['label' => t('Yes'), 'value' => '1', 'data' => ['indicator' => ['variant' => 'success']]],
            ['label' => t('No'), 'value' => '0', 'data' => ['indicator' => ['variant' => 'empty']]],
            ...self::booleanEnvOptions(),
        ];

        $booleanMenu = fn(string $label, string $name) => Field::make($label, Combobox::make($name)
            ->options($booleanOptions)
            ->requireOptionMatch())
            ->tip(t('This can be set to an environment variable with a boolean value ({examples}).', [
                'examples' => '`yes`/`no`/`true`/`false`/`on`/`off`/`0`/`1`',
            ]));

        $strategyMenu = fn(string $name, array $options) => Combobox::make($name)
            ->options([
                ...self::choiceOptions($options),
                ...SelectOptions::getEnvOptions(array_keys($options)),
            ])
            ->requireOptionMatch();

        $settingsFields = [
            $booleanMenu(t('Auto Set New Cart Addresses', category: 'commerce'), 'autoSetNewCartAddresses')
                ->instructions(t('Whether the user’s primary shipping and billing addresses should be set automatically on new carts.', category: 'commerce')),
            $booleanMenu(t('Auto Set Cart Shipping Method Option', category: 'commerce'), 'autoSetCartShippingMethodOption')
                ->instructions(t('Whether the first available shipping method option should be set automatically on carts.', category: 'commerce')),
            $booleanMenu(t('Auto Set Payment Source', category: 'commerce'), 'autoSetPaymentSource')
                ->instructions(t('Whether the user’s primary payment source should be set automatically on new carts.', category: 'commerce')),
            $booleanMenu(t('Allow Empty Cart On Checkout', category: 'commerce'), 'allowEmptyCartOnCheckout'),
            $booleanMenu(t('Allow Checkout Without Payment', category: 'commerce'), 'allowCheckoutWithoutPayment'),
            $booleanMenu(t('Allow Partial Payment On Checkout', category: 'commerce'), 'allowPartialPaymentOnCheckout'),
            Field::make(t('Free Order Payment Strategy', category: 'commerce'), $strategyMenu('freeOrderPaymentStrategy', $storeModel->getFreeOrderPaymentStrategyOptions()))
                ->instructions(t('Strategy to apply when an order is free or has a zero balance.', category: 'commerce'))
                ->required(),
            Field::make(t('Minimum Total Price Strategy', category: 'commerce'), $strategyMenu('minimumTotalPriceStrategy', $storeModel->getMinimumTotalPriceStrategyOptions()))
                ->instructions(t('Strategy to apply when calculating the minimum order price.', category: 'commerce'))
                ->required(),
            $booleanMenu(t('Require Shipping Address At Checkout', category: 'commerce'), 'requireShippingAddressAtCheckout'),
            $booleanMenu(t('Require Billing Address At Checkout', category: 'commerce'), 'requireBillingAddressAtCheckout'),
            $booleanMenu(t('Require Shipping Method Selection At Checkout', category: 'commerce'), 'requireShippingMethodSelectionAtCheckout'),
            $booleanMenu(t('Use Billing Address For Tax', category: 'commerce'), 'useBillingAddressForTax'),
            $booleanMenu(t('Validate Business Tax ID as Vat ID', category: 'commerce'), 'validateOrganizationTaxIdAsVatId'),
            Field::make(t('Order Reference Number Format', category: 'commerce'), Text::make('orderReferenceFormat')
                ->monospace()
                ->textExpanderTriggers(SelectOptions::getEnvTextExpanderTriggers()))
                ->instructions(t('A friendly reference number will be generated based on this format when a cart is completed and becomes an order. For example {ex1}, or<br> {ex2}. The result of this format must be unique.', [
                    'ex1' => Html::code('2018-{number[:7]}'),
                    'ex2' => Html::code("{{object.dateCompleted|date('y')}}-{{ seq(object.dateCompleted|date('y'), 8) }}"),
                ], category: 'commerce'))
                ->tip(sprintf(
                    '%s [%s](%s)',
                    t('Type `$` to choose an environment variable.'),
                    t('Learn more'),
                    'https://craftcms.com/docs/5.x/configure.html#control-panel-settings',
                )),
        ];

        return Ui::make()
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
            'autoSetNewCartAddresses' => self::booleanMenuValue($storeModel->getAutoSetNewCartAddresses(false)),
            'autoSetCartShippingMethodOption' => self::booleanMenuValue($storeModel->getAutoSetCartShippingMethodOption(false)),
            'autoSetPaymentSource' => self::booleanMenuValue($storeModel->getAutoSetPaymentSource(false)),
            'allowEmptyCartOnCheckout' => self::booleanMenuValue($storeModel->getAllowEmptyCartOnCheckout(false)),
            'allowCheckoutWithoutPayment' => self::booleanMenuValue($storeModel->getAllowCheckoutWithoutPayment(false)),
            'allowPartialPaymentOnCheckout' => self::booleanMenuValue($storeModel->getAllowPartialPaymentOnCheckout(false)),
            'freeOrderPaymentStrategy' => $storeModel->getFreeOrderPaymentStrategy(false),
            'minimumTotalPriceStrategy' => $storeModel->getMinimumTotalPriceStrategy(false),
            'requireShippingAddressAtCheckout' => self::booleanMenuValue($storeModel->getRequireShippingAddressAtCheckout(false)),
            'requireBillingAddressAtCheckout' => self::booleanMenuValue($storeModel->getRequireBillingAddressAtCheckout(false)),
            'requireShippingMethodSelectionAtCheckout' => self::booleanMenuValue($storeModel->getRequireShippingMethodSelectionAtCheckout(false)),
            'useBillingAddressForTax' => self::booleanMenuValue($storeModel->getUseBillingAddressForTax(false)),
            'validateOrganizationTaxIdAsVatId' => self::booleanMenuValue($storeModel->getValidateOrganizationTaxIdAsVatId(false)),
            'orderReferenceFormat' => $storeModel->getOrderReferenceFormat(false),
        ];
    }

    /**
     * Boolean environment variables, each showing what it currently resolves to.
     *
     * @return list<array<string, mixed>>
     */
    private static function booleanEnvOptions(): array
    {
        $groups = SelectOptions::getBooleanEnvOptions();
        $groups[0]['options'] = $groups[0]['options']
            ->map(function(array $option): array {
                $enabled = $option['data']['boolean'] === '1';

                return [
                    ...$option,
                    'data' => [
                        ...$option['data'],
                        'hint' => $enabled ? t('Yes') : t('No'),
                        'indicator' => ['variant' => $enabled ? 'success' : 'empty'],
                    ],
                ];
            })
            ->all();

        return $groups;
    }

    /**
     * The option a boolean setting selects: its environment variable, or Yes/No.
     */
    private static function booleanMenuValue(bool|string $value): string
    {
        if (is_string($value) && str_starts_with($value, '$')) {
            return $value;
        }

        return Env::normalizeBooleanValue($value) ? '1' : '0';
    }

    /**
     * The value a boolean setting stores: an environment variable reference, or a boolean.
     */
    private static function booleanMenuInput(mixed $value): bool|string
    {
        if (is_string($value) && str_starts_with($value, '$')) {
            return $value;
        }

        return Env::normalizeBooleanValue($value) ?? false;
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
        $store->setAutoSetNewCartAddresses(self::booleanMenuInput($request->input('autoSetNewCartAddresses')));
        $store->setAutoSetCartShippingMethodOption(self::booleanMenuInput($request->input('autoSetCartShippingMethodOption')));
        $store->setAutoSetPaymentSource(self::booleanMenuInput($request->input('autoSetPaymentSource')));
        $store->setAllowEmptyCartOnCheckout(self::booleanMenuInput($request->input('allowEmptyCartOnCheckout')));
        $store->setAllowCheckoutWithoutPayment(self::booleanMenuInput($request->input('allowCheckoutWithoutPayment')));
        $store->setAllowPartialPaymentOnCheckout(self::booleanMenuInput($request->input('allowPartialPaymentOnCheckout')));
        $store->setRequireShippingAddressAtCheckout(self::booleanMenuInput($request->input('requireShippingAddressAtCheckout')));
        $store->setRequireBillingAddressAtCheckout(self::booleanMenuInput($request->input('requireBillingAddressAtCheckout')));
        $store->setRequireShippingMethodSelectionAtCheckout(self::booleanMenuInput($request->input('requireShippingMethodSelectionAtCheckout')));
        $store->setUseBillingAddressForTax(self::booleanMenuInput($request->input('useBillingAddressForTax')));
        $store->setValidateOrganizationTaxIdAsVatId(self::booleanMenuInput($request->input('validateOrganizationTaxIdAsVatId')));
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
            return $this->asModelFailure($store, t('Couldn’t save the store.', category: 'commerce'), 'store');
        }

        if ($siteId = $request->input('siteId')) {
            $siteStore = collect($storesService->getAllSiteStores())->where('siteId', $siteId)->first();
            $siteStore->storeId = $store->id;
            $storesService->saveSiteStore($siteStore);
        }

        return $this->asModelSuccess($store, t('Store saved.', category: 'commerce'), 'store');
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
            '_search' => implode(' ', [
                t($s->getName(), category: 'site'),
                $s->handle,
                $s->getSiteNames()->join(' '),
                $s->getCurrency()?->getCode(),
            ]),
            'name' => [
                'label' => t($s->getName(), category: 'site'),
                'url' => Url::cpUrl('commerce/settings/stores/' . $s->id),
            ],
            'handle' => $s->handle,
            'sites' => $s->getSiteNames()->join(', '),
            'currency' => ['html' => Html::tag('code', Html::encode($s->getCurrency()?->getCode() ?? ''))],
            'primary' => $s->primary ? ['icon' => 'check', 'label' => t('Yes')] : '',
            'management' => [
                'label' => t('Store Management', category: 'commerce'),
                'items' => $menuItems[$s->handle],
            ],
            '_deletable' => !$s->primary,
        ])->all();

        $title = t('Stores', category: 'commerce');

        $showNewStoreButton = !$this->readOnly && $stores->count() < count(Sites::getAllSites());

        if ($showNewStoreButton) {
            $showNewStoreButton = ($this->plugin->is(Plugin::EDITION_PRO, '=')
                    && $stores->count() < Plugin::EDITION_PRO_STORE_LIMIT
                    && app(CatalogPricingRules::class)->canUseCatalogPricingRules())
                || ($this->plugin->is(Plugin::EDITION_ENTERPRISE, '=')
                    && app(CatalogPricingRules::class)->canUseCatalogPricingRules());
        }

        $form = Ui::make([
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
                ->searchable()
                ->toggleableColumns()
                ->createAction(
                    $showNewStoreButton ? t('New store', category: 'commerce') : null,
                    $showNewStoreButton ? Url::cpUrl('commerce/settings/stores/new') : null,
                )
                ->createActionInPageHeader()
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
            ->inertiaPage('Ui', [
                'ui' => $this->formResolver->resolve($form, new UiContext()),
                'contentMaxWidth' => false,
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
            return $this->asFailure(t('Couldn’t reorder stores.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function editSiteStores(): CpScreenResponse
    {
        $storesService = app(Stores::class);
        $sitesStores = $storesService->getAllSiteStores();
        $primaryStoreId = $storesService->getPrimaryStore()->id;

        $storeOptions = $storesService->getAllStores()->map(fn(Store $store) => [
            'label' => $store->getName(),
            'value' => $store->id,
        ])->all();

        $rows = [];
        $values = [];

        foreach (Sites::getAllSites() as $site) {
            $siteStore = $sitesStores->count() > 0 ? $sitesStores->firstWhere('siteId', $site->id) : null;
            $siteName = t($site->name, category: 'site');
            $storeId = session()->getOldInput("siteStores.$site->id.storeId", $siteStore->storeId ?? $primaryStoreId);
            $values[$site->id] = ['storeId' => $storeId];

            $rows[] = [
                'id' => $site->id,
                'site' => $siteName,
                'store' => ['html' => Select::make()
                    ->name("siteStores[$site->id][storeId]")
                    ->label(t('Store for {site}', ['site' => $siteName], category: 'commerce'))
                    ->labelSrOnly()
                    ->options($storeOptions)
                    ->selectAttributes(['data-site-id' => $site->id])
                    ->value($storeId)
                    ->disabled($this->readOnly)
                    ->toHtml(), ],
            ];
        }

        $table = Table::make('siteStores')
            ->columns([
                ['key' => 'site', 'label' => t('Site')],
                ['key' => 'store', 'label' => t('Store', category: 'commerce')],
            ])
            ->showFooter(false)
            ->rows($rows);

        $form = Ui::make([
            Field::make(null, SiteStores::make('siteStores')->table($table)),
        ]);

        $title = t('Sites');

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbsForSection(['label' => $title, 'href' => cp_url('commerce/settings/stores/sites')]))
            ->redirectUrl('commerce/settings/stores/sites')
            ->inertiaPage('Ui', [
                'contentMaxWidth' => false,
                'ui' => $this->formResolver->resolve($form, new UiContext(
                    values: ['siteStores' => $values],
                    mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
                )),
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'saveSiteStores']),
                ],
            ]);
    }

    public function saveSiteStores(Request $request): Response
    {
        $siteStoresData = $request->input('siteStores', []);
        $sitesStores = app(Stores::class)->getAllSiteStores();
        $stores = app(Stores::class)->getAllStores();

        foreach ($sitesStores as $siteStore) {
            if (isset($siteStoresData[$siteStore->siteId])) {
                $storeId = $siteStoresData[$siteStore->siteId]['storeId'];
                $siteStore->storeId = $storeId !== null && $storeId !== '' ? (int) $storeId : null;
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
