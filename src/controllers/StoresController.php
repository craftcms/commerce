<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use Illuminate\Support\Collection;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use craft\db\Table as CraftTable;

/**
 * Class Stores Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class StoresController extends BaseStoreManagementController
{
    /**
     * Edit a store.
     *
     * @param int|null $storeId The Store’s ID, if editing an existing Store
     * @param Store|null $storeModel The Store being edited, if there were any validation errors
     */
    public function actionEditStore(?int $storeId = null, ?Store $storeModel = null): Response
    {
        $storesService = Plugin::getInstance()->getStores();

        $brandNewStore = false;
        $allowCurrencyChange = false;

        if ($storeId !== null) {
            if ($storeModel === null) {
                $storeModel = $storesService->getStoreById($storeId);

                if (!$storeModel) {
                    throw new NotFoundHttpException('Store not found');
                }
            }

            $title = trim($storeModel->getName()) ?: Craft::t('app', 'Edit Store');
        } else {
            if ($storeModel === null) {
                $storeModel = new Store();
                $brandNewStore = true;
                $allowCurrencyChange = true;
            }

            $title = Craft::t('app', 'Create a new Store');
        }

        // Breadcrumbs
        $crumbs = [
            [
                'label' => Craft::t('commerce', 'Settings'),
                'url' => UrlHelper::url('commerce/settings'),
            ],
            [
                'label' => Craft::t('app', 'Stores'),
                'url' => UrlHelper::url('commerce/settings/stores'),
            ],
        ];

        $hasOrders = $storeModel->id && Order::find()
                ->trashed(null)
                ->storeId($storeModel->id)
                ->exists();

        if (!$hasOrders) {
            $allowCurrencyChange = true;
        }

        // map sites into select box options array
        $availableSiteOptions = collect(Craft::$app->getSites()->getAllSites())->map(function($site) {
            $availableForAssignmentToNewStores = Plugin::getInstance()->getStores()->getSiteIdsAvailableForAssignmentToNewStores();
            return [
                'label' => $site->name,
                'value' => $site->id,
                'disabled' => collect($availableForAssignmentToNewStores)->contains($site->id) === false,
            ];
        })->all();

        $currencyOptions = Plugin::getInstance()->getCurrencies()->getAllCurrenciesList();

        return $this->renderTemplate('commerce/settings/stores/_edit', [
            'brandNewStore' => $brandNewStore,
            'allowCurrencyChange' => $allowCurrencyChange,
            'title' => $title,
            'crumbs' => $crumbs,
            'store' => $storeModel,
            'currencyOptions' => $currencyOptions,
            'availableSiteOptions' => $availableSiteOptions,
            'freeOrderPaymentStrategyOptions' => $storeModel->getFreeOrderPaymentStrategyOptions(),
            'minimumTotalPriceStrategyOptions' => $storeModel->getMinimumTotalPriceStrategyOptions(),
        ]);
    }

    /**
     * Saves a store.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws BusyResourceException
     * @throws StaleResourceException
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionSaveStore(): ?Response
    {
        $this->requirePostRequest();

        $storesService = Plugin::getInstance()->getStores();
        $storeId = $this->request->getBodyParam('storeId');

        if ($storeId) {
            $store = $storesService->getStoreById($storeId);
            if (!$store) {
                throw new BadRequestHttpException("Invalid store ID: $storeId");
            }
        } else {
            $store = new Store();
        }

        $store->setName($this->request->getBodyParam('name'));
        $store->handle = $this->request->getBodyParam('handle');
        $store->setAutoSetNewCartAddresses($this->request->getBodyParam('autoSetNewCartAddresses'));
        $store->setAutoSetCartShippingMethodOption($this->request->getBodyParam('autoSetCartShippingMethodOption'));
        $store->setAutoSetPaymentSource($this->request->getBodyParam('autoSetPaymentSource'));
        $store->setAllowEmptyCartOnCheckout($this->request->getBodyParam('allowEmptyCartOnCheckout'));
        $store->setAllowCheckoutWithoutPayment($this->request->getBodyParam('allowCheckoutWithoutPayment'));
        $store->setAllowPartialPaymentOnCheckout($this->request->getBodyParam('allowPartialPaymentOnCheckout'));
        $store->setRequireShippingAddressAtCheckout($this->request->getBodyParam('requireShippingAddressAtCheckout'));
        $store->setRequireBillingAddressAtCheckout($this->request->getBodyParam('requireBillingAddressAtCheckout'));
        $store->setRequireShippingMethodSelectionAtCheckout($this->request->getBodyParam('requireShippingMethodSelectionAtCheckout'));
        $store->setUseBillingAddressForTax($this->request->getBodyParam('useBillingAddressForTax'));
        $store->setValidateOrganizationTaxIdAsVatId($this->request->getBodyParam('validateOrganizationTaxIdAsVatId'));
        $store->setOrderReferenceFormat($this->request->getBodyParam('orderReferenceFormat'));
        $store->setFreeOrderPaymentStrategy($this->request->getBodyParam('freeOrderPaymentStrategy'));
        $store->setMinimumTotalPriceStrategy($this->request->getBodyParam('minimumTotalPriceStrategy'));
        $store->primary = (bool)$this->request->getBodyParam('primary', $store->primary);

        if ($currency = $this->request->getBodyParam('currency')) {
            $store->setCurrency($currency);
        }

        if ($storeId && $savedStore = $storesService->getStoreById($storeId)) {
            $store->uid = $savedStore->uid;
            $store->sortOrder = $savedStore->sortOrder;
        } elseif (!$storeId) {
            $store->sortOrder = (new Query())->from(Table::STORES)->max('[[sortOrder]]') + 1;
        }

        // Save it
        if (!$store->validate() || !$storesService->saveStore($store)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save the store.'));

            // Send the store back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'storeModel' => $store,
            ]);

            return null;
        }

        // Create the site store relationship for this new order
        if ($siteId = $this->request->getBodyParam('siteId')) {
            $siteStore = collect($storesService->getAllSiteStores())->where('siteId', $siteId)->first();
            $siteStore->storeId = $store->id;
            $storesService->saveSiteStore($siteStore);
        }


        $this->setSuccessFlash(Craft::t('app', 'Store saved.'));
        return $this->redirectToPostedUrl($store);
    }


    /**
     * @return Response
     * @throws \yii\base\InvalidConfigException
     */
    public function actionStoresIndex(): Response
    {
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        // Breadcrumbs
        $crumbs = [
            [
                'label' => Craft::t('commerce', 'Settings'),
                'url' => UrlHelper::url('commerce/settings/stores'),
            ],
        ];

        $menuItems = [];
        $stores->each(function(Store $s) use (&$menuItems) {
            $m = [];
            $m[] = [
                'label' => Craft::t('commerce', 'Payment Currencies'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/payment-currencies'),
            ];

            $m[] = [
                'label' => Craft::t('commerce', 'Discounts'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/discounts'),
            ];

            if (Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules()) {
                $m[] = [
                    'label' => Craft::t('commerce', 'Pricing Rules'),
                    'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/pricing-rules'),
                ];
            } else {
                $m[] = [
                    'label' => Craft::t('commerce', 'Sales'),
                    'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/sales'),
                ];
            }

            $m[] = [
                'label' => Craft::t('commerce', 'Shipping Methods'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/shippingmethods'),
            ];

            $m[] = [
                'label' => Craft::t('commerce', 'Shipping Zones'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/shippingzones'),
            ];

            $m[] = [
                'label' => Craft::t('commerce', 'Shipping Categories'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/shippingcategories'),
            ];

            $m[] = [
                'label' => Craft::t('commerce', 'Tax Rates'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/taxrates'),
            ];

            $m[] = [
                'label' => Craft::t('commerce', 'Tax Zones'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/taxzones'),
            ];

            $m[] = [
                'label' => Craft::t('commerce', 'Tax Categories'),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $s->handle . '/taxcategories'),
            ];

            $menuItems[$s->handle] = $m;
        });


        return $this->renderTemplate('commerce/settings/stores/index', [
            'stores' => $stores,
            'crumbs' => $crumbs,
            'sitesStores' => Plugin::getInstance()->getStores()->getAllSiteStores(),
            'primaryStoreId' => Plugin::getInstance()->getStores()->getPrimaryStore()->id,
            'menuItems' => $menuItems,
        ]);
    }

    /**
     * Deletes a store.
     *
     * @return Response
     */
    public function actionDeleteStore(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $siteId = $this->request->getRequiredBodyParam('id');

        Plugin::getInstance()->getStores()->deleteStoreById($siteId);

        return $this->asSuccess();
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionReorderStores(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));

        if (!Plugin::getInstance()->getStores()->reorderStores($ids)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder stores.'));
        }

        return $this->asSuccess();
    }

    /**
     * @param Collection|null $sitesStores
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionEditSiteStores(Collection $sitesStores = null): Response
    {
        // Breadcrumbs
        $crumbs = [
            [
                'label' => Craft::t('commerce', 'Settings'),
                'url' => UrlHelper::url('commerce/settings/stores'),
            ],
        ];

        return $this->renderTemplate('commerce/settings/stores/_siteStore', [
            'crumbs' => $crumbs,
            'stores' => Plugin::getInstance()->getStores()->getAllStores(),
            'sites' => Craft::$app->getSites()->getAllSites(),
            'sitesStores' => $sitesStores ?? Plugin::getInstance()->getStores()->getAllSiteStores(),
            'primaryStoreId' => Plugin::getInstance()->getStores()->getPrimaryStore()->id,
        ]);
    }

    /**
     * Saves the site settings records
     *
     * @return ?Response
     */
    public function actionSaveSiteStores(): ?Response
    {
        $siteStoresData = $this->request->getBodyParam('siteStores', []);
        $sitesStores = Plugin::getInstance()->getStores()->getAllSiteStores();
        $stores = Plugin::getInstance()->getStores()->getAllStores();

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
                Craft::t('commerce', '{storeNames} {num, plural, =1{has} other{have}} not been assigned to a site.', [
                    'storeNames' => implode(', ', $unassignedStores),
                    'num' => count($unassignedStores),
                ]),
                routeParams: ['sitesStores' => collect($sitesStores)]
            );
        }

        foreach ($sitesStores as $siteStore) {
            Plugin::getInstance()->getStores()->saveSiteStore($siteStore);
        }

        return $this->asSuccess(Craft::t('commerce', 'Site store mapping saved.'));
    }
}
