<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

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

        return $this->renderTemplate('commerce/settings/stores/_edit', [
            'brandNewStore' => $brandNewStore,
            'title' => $title,
            'crumbs' => $crumbs,
            'store' => $storeModel,
        ]);
    }

    /**
     * Saves a store.
     *
     * @return Response|null
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

        // Save it
        if (!$storesService->saveStore($store)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save the store.'));

            // Send the store back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'storeModel' => $store,
            ]);

            return null;
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
     * Saves the site settings records
     *
     * @return Response
     */
    public function actionSaveSiteSettings(): Response
    {
    }
}
