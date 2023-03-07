<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Stores Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class StoresController extends BaseStoreSettingsController
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

        // map sites into select box options array
        $availableSiteOptions = collect(Craft::$app->getSites()->getAllSites())->map(function($site) {
            $availableForAssignmentToNewStores = Plugin::getInstance()->getStores()->getSiteIdsAvailableForAssignmentToNewStores();
            return [
                'label' => $site->name,
                'value' => $site->id,
                'disabled' => collect($availableForAssignmentToNewStores)->contains($site->id) === false,
            ];
        })->all();

        return $this->renderTemplate('commerce/settings/stores/_edit', [
            'brandNewStore' => $brandNewStore,
            'title' => $title,
            'crumbs' => $crumbs,
            'store' => $storeModel,
            'availableSiteOptions' => $availableSiteOptions,
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
        if ($this->request->getBodyParam('primary') !== null) {
            $store->primary = (bool)$this->request->getBodyParam('primary');
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

        return $this->renderTemplate('commerce/settings/stores/index', [
            'stores' => $stores,
            'crumbs' => $crumbs,
            'sitesStores' => Plugin::getInstance()->getStores()->getAllSiteStores(),
            'primaryStoreId' => Plugin::getInstance()->getStores()->getPrimaryStore()->id,
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
     * @param array|null $siteStores
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionEditSiteStores(array $siteStores = null): Response
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
            'sitesStores' => $siteStores ?? Plugin::getInstance()->getStores()->getAllSiteStores(),
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
        $siteStores = Plugin::getInstance()->getStores()->getAllSiteStores();
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        foreach ($siteStores as $siteStore) {
            if (isset($siteStoresData[$siteStore->siteId])) {
                $siteStore->storeId = $siteStoresData[$siteStore->siteId]['storeId'];
            }
        }

        $unassignedStores = [];
        foreach ($stores as $store) {
            $storeAssigned = false;
            foreach ($siteStores as $siteStore) {
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
                Craft::t('commerce', '{storeNames} have not been assigned to a site.', [
                    'storeNames' => implode(', ', $unassignedStores),
                ]),
                routeParams: ['siteStores' => $siteStores]
            );
        }

        foreach ($siteStores as $siteStore) {
            Plugin::getInstance()->getStores()->saveSiteStore($siteStore);
        }

        return $this->asSuccess(Craft::t('commerce', 'Site store mapping saved.'));
    }
}
