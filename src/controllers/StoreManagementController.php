<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\helpers\Cp as CommerceCp;
use craft\commerce\models\StoreSettings;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\helpers\Cp;
use craft\models\Site;
use craft\web\twig\TemplateLoaderException;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\Response as YiiResponse;

/**
 * Class Store Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreManagementController extends BaseStoreManagementController
{
    public function actionIndex(): Response
    {
        $user = Craft::$app->getUser();
        /** @var Site|HasStoreInterface $site */
        $site = Cp::requestedSite();
        
        if ($user->checkPermission('commerce-manageGeneralStoreSettings')) {
            return $this->redirect($site->getStore()->getStoreSettingsUrl());
        }

        if ($user->checkPermission('commerce-managePaymentCurrencies')) {
            return $this->redirect($site->getStore()->getStoreSettingsUrl('payment-currencies'));
        }

        if ($user->checkPermission('commerce-managePromotions')) {
            return $this->redirect($site->getStore()->getStoreSettingsUrl('discounts'));
        }

        if ($user->checkPermission('commerce-manageShipping')) {
            return $this->redirect($site->getStore()->getStoreSettingsUrl('shipping'));
        }

        if ($user->checkPermission('commerce-manageTaxes')) {
            return $this->redirect($site->getStore()->getStoreSettingsUrl('taxrates'));
        }

        return $this->renderTemplate('commerce/store-management/index');
    }

    /**
     * @return YiiResponse
     * @throws TemplateLoaderException
     * @throws InvalidConfigException
     */
    public function actionEdit(StoreSettings $storeSettings = null, ?string $storeHandle = null): Response
    {
        $this->requirePermission('commerce-manageGeneralStoreSettings');

        $variables = compact('storeSettings', 'storeHandle');

        if (!$variables['storeSettings']) {
            if ($variables['storeHandle']) {
                // Store has the same ID as Store Settings
                $variables['store'] = Plugin::getInstance()->getStores()->getStoreByHandle($variables['storeHandle']);

                if (!$variables['store']) {
                    throw new HttpException(404);
                }

                $variables['storeSettings'] = $variables['store']->getSettings();
            } else {
                // Attempt to redirect the user to the correct store settings for the site they were working on
                /** @var Site|StoreBehavior $site */
                $site = Cp::requestedSite();
                return $this->redirect($site->getStore()->getStoreSettingsUrl());
            }
        }

        $addressesService = Craft::$app->getAddresses();
        $allCountries = $addressesService->getCountryRepository()->getList(Craft::$app->language);

        $locationFieldHtml = Cp::elementCardHtml($variables['storeSettings']->getLocationAddress(), [
            'context' => 'field',
            'inputName' => 'locationAddressId',
            'showActionMenu' => true,
        ]);

        // Countries market condition field HTML
        $condition = $variables['storeSettings']->getMarketAddressCondition();
        $condition->mainTag = 'div';
        $condition->name = 'marketAddressCondition';
        $condition->id = 'marketAddressCondition';
        $marketAddressConditionFieldHtml = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Order Address Condition'),
            'instructions' => Craft::t('app', 'Only allow orders with addresses that match the following rules:'),
        ]);

        // Countries allowed field HTML
        $countriesField = Cp::selectizeFieldHtml([
            'label' => Craft::t('commerce', 'Country List'),
            'instructions' => Craft::t('commerce', 'The countries that orders are allowed to be placed from.'),
            'id' => 'countries',
            'name' => 'countries',
            'multi' => true,
            'values' => $variables['storeSettings']->getCountries(),
            'options' => $allCountries,
            'errors' => $variables['storeSettings']->getErrors('countries'),
            'allowEmptyOption' => true,
        ]);


        // Inventory locations field HTML
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($variables['store']->id);
        $allInventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $currentUser = Craft::$app->getUser()->getIdentity();

        $locationsCount = count($allInventoryLocations);
        $userCanCreate = $currentUser->can('commerce-manageInventoryLocations');
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
                'label' => Craft::t('commerce', 'Inventory Locations'),
                'instructions' => Craft::t('commerce', 'The inventory locations this store uses.'),
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

        // Variables
        $variables['marketAddressConditionField'] = $marketAddressConditionFieldHtml;
        $variables['countriesField'] = $countriesField;
        $variables['locationField'] = $locationFieldHtml;
        $variables['inventoryLocationsField'] = $inventoryLocationsField;
        $variables['storeSettingsNav'] = $this->getStoreSettingsNav();

        return $this->renderTemplate('commerce/store-management/general/_edit', $variables);
    }

    /**
     * @return YiiResponse|null
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws Exception
     */
    public function actionSave(): ?YiiResponse
    {
        $this->requirePermission('commerce-manageGeneralStoreSettings');

        $storeId = Craft::$app->getRequest()->getBodyParam('id');
        $store = Plugin::getInstance()->getStores()->getStoreById($storeId);
        $storeSettings = Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($storeId);
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($locationAddressId = $this->request->getBodyParam('locationAddressId')) {
            /** @var Address|null $locationAddress */
            $locationAddress = Address::find()->id($locationAddressId)->one();
            if ($locationAddress) {
                $storeSettings->setLocationAddress($locationAddress);
            }
        }
        $marketAddressCondition = $this->request->getBodyParam('marketAddressCondition') ?? new ZoneAddressCondition();
        $storeSettings->setMarketAddressCondition($marketAddressCondition);
        $countries = $this->request->getBodyParam('countries') ?: [];
        $storeSettings->setCountries($countries);

        // Save inventory locations
        if ($currentUser->can('commerce-manageInventoryLocations')) {
            $inventoryLocations = Craft::$app->getRequest()->getParam('inventoryLocations');

            if (!$inventoryLocations) {
                return $this->asFailure(
                    Craft::t('commerce', 'Missing a default inventory location.'),

                );
            }

            if (!Plugin::getInstance()->getInventoryLocations()->saveStoreInventoryLocations($store, $inventoryLocations)) {
                return $this->asFailure(
                    Craft::t('commerce', 'Inventory locations not saved.')
                );
            }
        }

        if (!$storeSettings->validate() || !Plugin::getInstance()->getStoreSettings()->saveStoreSettings($storeSettings)) {
            return $this->asModelFailure(
                model: $storeSettings,
                message: Craft::t('commerce', 'Couldn’t save store.'),
                modelName: 'storeSettings',
            );
        }

        return $this->asModelSuccess(
            model: $storeSettings,
            message: Craft::t('commerce', 'Store saved.'),
            modelName: 'storeSettings',
        );
    }
}
