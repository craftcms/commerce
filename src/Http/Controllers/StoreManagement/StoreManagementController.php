<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use craft\helpers\Cp;
use CraftCms\Cms\Form\Controls\Address as AddressControl;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Controls\ComboboxCreate;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\MarkdownContent;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Address\Conditions\ZoneAddressCondition;
use CraftCms\Commerce\Http\Controllers\InventoryLocationsController;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Store\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class StoreManagementController extends BaseStoreManagementController
{
    /** The Address element's own geographic fields — everything {@see AddressControl} renders. */
    private const array LOCATION_ADDRESS_FIELDS = [
        'addressLine1',
        'addressLine2',
        'addressLine3',
        'administrativeArea',
        'locality',
        'dependentLocality',
        'postalCode',
        'sortingCode',
    ];

    public function __construct(
        private Plugin $plugin,
        FormResolver $formResolver,
    ) {
        parent::__construct($formResolver);
    }

    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('General', category: 'commerce'), 'href' => $store->getStoreSettingsUrl()];
    }

    public function index(): Response|CpScreenResponse
    {
        $site = Cp::requestedSite();
        /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
        $store = $site->getStore();

        if (currentUser()?->can('commerce-manageGeneralStoreSettings')) {
            return redirect($store->getStoreSettingsUrl());
        }

        if (currentUser()?->can('commerce-managePaymentCurrencies')) {
            return redirect($store->getStoreSettingsUrl('payment-currencies'));
        }

        if (currentUser()?->can('commerce-managePromotions')) {
            return redirect($store->getStoreSettingsUrl('discounts'));
        }

        if (currentUser()?->can('commerce-manageShipping')) {
            return redirect($store->getStoreSettingsUrl('shipping'));
        }

        if (currentUser()?->can('commerce-manageTaxes')) {
            return redirect($store->getStoreSettingsUrl('taxrates'));
        }

        return $this->cpScreenResponse($store)
            ->title(t('Store Management', category: 'commerce'))
            ->crumbs($this->crumbs($store))
            ->contentHtml(Html::tag(
                'p',
                t('No access given to any specific store management features.', category: 'commerce')
            ));
    }

    public function edit(?string $storeHandle = null): Response|CpScreenResponse
    {
        abort_unless(currentUser()?->can('commerce-manageGeneralStoreSettings'), 403);

        if (!$storeHandle) {
            $site = Cp::requestedSite();
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            return redirect($site->getStore()->getStoreSettingsUrl());
        }

        $store = app(Stores::class)->getStoreByHandle($storeHandle);
        abort_if($store === null, 404);

        $values = $this->initialValues($store);

        $form = $this->formResolver->resolve(
            $this->buildForm($store, $values),
            new FormContext(values: $values, refreshable: true),
        );

        return $this->cpScreenResponse($store)
            ->title(t('General', category: 'commerce'))
            ->crumbs($this->crumbs($store))
            ->action('commerce/store-management/save')
            ->redirectUrl($store->getStoreSettingsUrl())
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => action([self::class, 'renderForm']),
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form tree for the values currently in progress on the
     * client, so changing the store location's country can reveal the right set of
     * country-specific address fields without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.id' => ['required', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $store = app(Stores::class)->getStoreById((int) $values['id']);
        abort_if($store === null, 404);

        $values = array_replace($this->initialValues($store), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($store, $values),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    /** @return array<string, mixed> */
    private function initialValues(Store $store): array
    {
        $storeSettings = $store->getSettings();
        $locationAddress = $storeSettings->getLocationAddress();

        $values = [
            'id' => $store->id,
            'locationAddressCountryCode' => $locationAddress->countryCode,
            'locationAddress' => $locationAddress->toArray(self::LOCATION_ADDRESS_FIELDS),
            'countries' => $storeSettings->getCountries(),
            'marketAddressCondition' => $storeSettings->getMarketAddressCondition()->getConfig(),
        ];

        if (currentUserElement()?->can('commerce-manageInventoryLocations')) {
            $values['inventoryLocations'] = app(InventoryLocations::class)->getInventoryLocations($store->id)
                ->map(fn(InventoryLocation $inventoryLocation) => $inventoryLocation->id)
                ->all();
        }

        return $values;
    }

    /** @param array<string, mixed> $values */
    private function buildForm(Store $store, array $values): Form
    {
        $countryOptions = collect(Addresses::getCountryList())
            ->map(fn(string $label, string $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();

        $formNodes = [
            HiddenField::make('id'),

            Heading::make('store-location-heading', t('Store Location', category: 'commerce')),
            // Plain text in the legacy Twig too — a bare `<p>`, not a boxed callout.
            MarkdownContent::make('store-location-intro', t('This is the address where your store is located. It may be used by various plugins to determine things like shipping and taxes. It could also be used in PDF receipts.', category: 'commerce'))
                ->displayInPane(false),

            // Reactive: the address fields below depend on which fields the selected
            // country actually uses (e.g. "State" only appears for some countries) — mirrors
            // how every other Address-editing screen in the app pairs a country select with
            // the address fields, just built directly here instead of through the Address
            // element's own field layout (this is the store's own location, not a
            // user-facing Address field).
            Field::make(t('Country', category: 'commerce'), Choice::make('locationAddressCountryCode')
                ->options($countryOptions)
                ->reactive())
                ->required(),
            Field::make(control: AddressControl::make('locationAddress')
                ->countryCode($values['locationAddressCountryCode'])),

            Heading::make('store-markets-heading', t('Store Markets', category: 'commerce')),
            // A plain multi-select Choice would render every option as its own checkbox — fine
            // for a handful of options, unusable for a ~250-country list. Combobox gives the
            // same searchable, chip-based picker the legacy selectize field did.
            Field::make(t('Country List', category: 'commerce'), Combobox::make('countries')
                ->multiple()
                ->options($countryOptions))
                ->instructions(t('The countries that orders are allowed to be placed from.', category: 'commerce')),

            // Neither of these two strings has a category — matches the legacy controller,
            // which called t() on them without one too (falls back to the 'app' category;
            // there's no local lang/en/app.php in either repo to verify against the way
            // commerce.php settles everything else).
            Field::make(t('Order Address Condition'), ConditionBuilder::make('marketAddressCondition')
                ->conditionClass(ZoneAddressCondition::class))
                ->instructions(t('Only allow orders with addresses that match the following rules:')),
        ];

        if (currentUserElement()?->can('commerce-manageInventoryLocations')) {
            $canCreate = false;
            $limit = Plugin::EDITION_PRO_STORE_LIMIT;
            $locationsCount = app(InventoryLocations::class)->getAllInventoryLocations()->count();

            if ($locationsCount < $limit) {
                $canCreate = true;
            }
            if ($this->plugin->is(Plugin::EDITION_ENTERPRISE, '=')) {
                $limit = null;
                $canCreate = true;
            }

            $inventoryLocationOptions = app(InventoryLocations::class)->getAllInventoryLocations()
                ->map(fn(InventoryLocation $inventoryLocation) => ['label' => $inventoryLocation->name, 'value' => (string) $inventoryLocation->id])
                ->values()
                ->all();
            if ($canCreate) {
                // No `category` here, matching InventoryLocationsController::edit()'s own title
                // for this same screen — not a commerce.php string.
                $inventoryLocationOptions[] = ['label' => t('Create a new inventory location'), 'value' => '__add__'];
            }

            $inventoryLocationsControl = ComboboxCreate::make('inventoryLocations')
                ->multiple()
                ->options($inventoryLocationOptions)
                ->limit($limit);

            if ($canCreate) {
                $inventoryLocationsControl
                    ->createUrl(action([InventoryLocationsController::class, 'edit']))
                    ->resultKey('inventoryLocation');
            }

            $formNodes[] = Heading::make('inventory-locations-heading', t('Inventory Locations', category: 'commerce'));
            $formNodes[] = Field::make(t('Inventory Locations', category: 'commerce'), $inventoryLocationsControl)
                ->instructions(t('The inventory locations this store uses.', category: 'commerce'));
        }

        return Form::make($formNodes);
    }

    public function save(Request $request): Response
    {
        abort_unless(currentUser()?->can('commerce-manageGeneralStoreSettings'), 403);

        $storeId = (int)$request->input('id');
        $this->requireStoreAccess($storeId);
        $store = app(Stores::class)->getStoreById($storeId);
        $storeSettings = app(StoreSettings::class)->getStoreSettingsById($storeId);
        $currentUser = currentUserElement();

        // The location address always exists (getLocationAddress() auto-creates a blank one) —
        // unlike the legacy Twig screen, which only ever let you swap in a *different*,
        // already-existing address element (editing this one's own fields happened entirely
        // out-of-band, via a double-click-to-open element-editor slideout), the address's own
        // fields are now posted right alongside everything else here and need saving as a real
        // element in their own right.
        $locationAddress = $storeSettings->getLocationAddress();
        $locationAddress->countryCode = $request->input('locationAddressCountryCode') ?: $locationAddress->countryCode;
        $addressFields = (array)$request->input('locationAddress');
        foreach (self::LOCATION_ADDRESS_FIELDS as $field) {
            if (array_key_exists($field, $addressFields)) {
                $locationAddress->$field = $addressFields[$field] ?: null;
            }
        }

        if (!Elements::saveElement($locationAddress)) {
            return $this->asModelFailure(
                model: $locationAddress,
                message: t('Couldn’t save store location address.', category: 'commerce'),
                modelName: 'locationAddress',
            );
        }
        $storeSettings->setLocationAddress($locationAddress);

        $marketAddressCondition = $request->input('marketAddressCondition') ?? new ZoneAddressCondition();
        $storeSettings->setMarketAddressCondition($marketAddressCondition);
        $countries = $request->input('countries') ?: [];
        $storeSettings->setCountries($countries);

        if ($currentUser?->can('commerce-manageInventoryLocations')) {
            $inventoryLocations = $request->input('inventoryLocations');

            if (!$inventoryLocations) {
                return $this->asFailure(t('Missing a default inventory location.', category: 'commerce'));
            }

            if (!app(InventoryLocations::class)->saveStoreInventoryLocations($store, $inventoryLocations)) {
                return $this->asFailure(t('Inventory locations not saved.', category: 'commerce'));
            }
        }

        if (!$storeSettings->validate() || !app(StoreSettings::class)->saveStoreSettings($storeSettings)) {
            return $this->asModelFailure(
                model: $storeSettings,
                message: t('Couldn’t save store.', category: 'commerce'),
                modelName: 'storeSettings',
            );
        }

        return $this->asModelSuccess(
            model: $storeSettings,
            message: t('Store saved.', category: 'commerce'),
            modelName: 'storeSettings',
        );
    }
}
