<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\base\HasStoreInterface;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\helpers\Cp as CommerceCp;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class StoreManagementController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(): Response|CpScreenResponse
    {
        /** @var Site|HasStoreInterface $site */
        $site = Cp::requestedSite();

        if (currentUser()?->can('commerce-manageGeneralStoreSettings')) {
            return redirect($site->getStore()->getStoreSettingsUrl());
        }

        if (currentUser()?->can('commerce-managePaymentCurrencies')) {
            return redirect($site->getStore()->getStoreSettingsUrl('payment-currencies'));
        }

        if (currentUser()?->can('commerce-managePromotions')) {
            return redirect($site->getStore()->getStoreSettingsUrl('discounts'));
        }

        if (currentUser()?->can('commerce-manageShipping')) {
            return redirect($site->getStore()->getStoreSettingsUrl('shipping'));
        }

        if (currentUser()?->can('commerce-manageTaxes')) {
            return redirect($site->getStore()->getStoreSettingsUrl('taxrates'));
        }

        return $this->storeManagementCpScreen($site->getStore()->handle)
            ->contentHtml(Html::tag(
                'p',
                t('No access given to any specific store management features.', category: 'commerce')
            ));
    }

    public function edit(?string $storeHandle = null): Response|CpScreenResponse
    {
        abort_unless(currentUser()?->can('commerce-manageGeneralStoreSettings'), 403);

        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            abort_if($store === null, 404);
            $storeSettings = $store->getSettings();
        } else {
            /** @var Site $site */
            $site = Cp::requestedSite();
            return redirect($site->getStore()->getStoreSettingsUrl());
        }

        $allCountries = Addresses::getCountryRepository()->getList(\Craft::$app->language);

        $locationFieldHtml = Cp::elementCardHtml($storeSettings->getLocationAddress(), [
            'context' => 'field',
            'inputName' => 'locationAddressId',
            'showActionMenu' => true,
        ]);

        $condition = $storeSettings->getMarketAddressCondition();
        $condition->mainTag = 'div';
        $condition->name = 'marketAddressCondition';
        $condition->id = 'marketAddressCondition';
        $marketAddressConditionFieldHtml = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => t('Order Address Condition'),
            'instructions' => t('Only allow orders with addresses that match the following rules:'),
        ]);

        $countriesField = Cp::selectizeFieldHtml([
            'label' => t('Country List', category: 'commerce'),
            'instructions' => t('The countries that orders are allowed to be placed from.', category: 'commerce'),
            'id' => 'countries',
            'name' => 'countries',
            'multi' => true,
            'values' => $storeSettings->getCountries(),
            'options' => $allCountries,
            'errors' => $storeSettings->getErrors('countries'),
            'allowEmptyOption' => true,
        ]);

        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($store->id);
        $allInventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $currentUser = currentUserElement();

        $locationsCount = count($allInventoryLocations);
        $userCanCreate = $currentUser?->can('commerce-manageInventoryLocations');
        $inventoryLocationsField = '';

        if ($userCanCreate) {
            $canCreate = false;

            $limit = Plugin::EDITION_PRO_STORE_LIMIT;
            if ($locationsCount < $limit) {
                $canCreate = true;
            }

            if (Plugin::getInstance()->is(Plugin::EDITION_ENTERPRISE, '=')) {
                $limit = null;
                $canCreate = true;
            }

            $config = [
                'label' => t('Inventory Locations', category: 'commerce'),
                'instructions' => t('The inventory locations this store uses.', category: 'commerce'),
                'id' => 'inventoryLocations',
                'name' => 'inventoryLocations[]',
                'values' => $inventoryLocations,
                'create' => $canCreate,
            ];

            if ($limit !== null) {
                $config['limit'] = $limit;
            }

            $inventoryLocationsField = CommerceCp::inventoryLocationFieldHtml($config);
        }

        return $this->storeManagementCpScreen($storeHandle)
            ->action('commerce/store-management/save')
            ->redirectUrl($store->getStoreSettingsUrl())
            ->submitButtonLabel(t('Save'))
            ->contentTemplate('commerce/store-management/general/_edit', [
                'store' => $store,
                'storeHandle' => $storeHandle,
                'storeSettings' => $storeSettings,
                'marketAddressConditionField' => $marketAddressConditionFieldHtml,
                'countriesField' => $countriesField,
                'locationField' => $locationFieldHtml,
                'inventoryLocationsField' => $inventoryLocationsField,
            ]);
    }

    public function save(Request $request): Response
    {
        abort_unless(currentUser()?->can('commerce-manageGeneralStoreSettings'), 403);

        $storeId = $request->input('id');
        $store = Plugin::getInstance()->getStores()->getStoreById($storeId);
        $storeSettings = Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($storeId);
        $currentUser = currentUserElement();

        if ($locationAddressId = $request->input('locationAddressId')) {
            $locationAddress = Address::find()->id($locationAddressId)->one();
            if ($locationAddress) {
                $storeSettings->setLocationAddress($locationAddress);
            }
        }
        $marketAddressCondition = $request->input('marketAddressCondition') ?? new ZoneAddressCondition();
        $storeSettings->setMarketAddressCondition($marketAddressCondition);
        $countries = $request->input('countries') ?: [];
        $storeSettings->setCountries($countries);

        if ($currentUser?->can('commerce-manageInventoryLocations')) {
            $inventoryLocations = $request->input('inventoryLocations');

            if (!$inventoryLocations) {
                return $this->asFailure(t('Missing a default inventory location.', category: 'commerce'));
            }

            if (!Plugin::getInstance()->getInventoryLocations()->saveStoreInventoryLocations($store, $inventoryLocations)) {
                return $this->asFailure(t('Inventory locations not saved.', category: 'commerce'));
            }
        }

        if (!$storeSettings->validate() || !Plugin::getInstance()->getStoreSettings()->saveStoreSettings($storeSettings)) {
            return $this->asModelFailure(
                model: $storeSettings,
                message: t('Couldn\'t save store.', category: 'commerce'),
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
