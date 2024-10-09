<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Categories Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategoriesController extends BaseShippingSettingsController
{
    /**
     * @param string|null $storeHandle
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($store->id);
        $variables = [
            'shippingCategories' => $shippingCategories,
            'storeHandle' => $store->handle,
            'store' => $store,
        ];
        return $this->renderTemplate('commerce/store-management/shipping/shippingcategories/index', $variables);
    }

    /**
     * @param int|null $id
     * @param ShippingCategory|null $shippingCategory
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ShippingCategory $shippingCategory = null): Response
    {
        $variables = [
            'id' => $id,
            'shippingCategory' => $shippingCategory,
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
            'storeHandle' => $storeHandle,
        ];

        $store = null;
        if ($storeHandle !== null) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        }

        $store = $store ?? Plugin::getInstance()->getStores()->getPrimaryStore();

        if (!$variables['shippingCategory']) {
            if ($variables['id']) {
                $variables['shippingCategory'] = Plugin::getInstance()
                    ->getShippingCategories()
                    ->getShippingCategoryById($variables['id'], $store->id);

                if (!$variables['shippingCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingCategory'] = Craft::createObject([
                    'class' => ShippingCategory::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($variables['shippingCategory']->id) {
            $variables['title'] = $variables['shippingCategory']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping category');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingCategory'], prepend: true);

        $variables['productTypesOptions'] = [];
        if (!empty($variables['productTypes'])) {
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', function($row) {
                return ['label' => $row->name, 'value' => $row->id];
            });
        }

        $allShippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($store->id);
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && $allShippingCategories->count() === 1 && $allShippingCategories->firstWhere('id', $variables['id']);

        return $this->renderTemplate('commerce/store-management/shipping/shippingcategories/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @noinspection Duplicates
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shippingCategory = new ShippingCategory();

        // Shared attributes
        $shippingCategory->id = $this->request->getBodyParam('shippingCategoryId');
        $shippingCategory->storeId = $this->request->getBodyParam('storeId');
        $shippingCategory->name = $this->request->getBodyParam('name');
        $shippingCategory->handle = $this->request->getBodyParam('handle');
        $shippingCategory->description = $this->request->getBodyParam('description');
        $shippingCategory->default = (bool)$this->request->getBodyParam('default');

        // Set the new product types
        $postedProductTypes = $this->request->getBodyParam('productTypes', []) ?: [];
        $productTypes = [];
        foreach ($postedProductTypes as $productTypeId) {
            if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $shippingCategory->setProductTypes($productTypes);


        // Save it
        if (!Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
            return $this->asModelFailure(
                $shippingCategory,
                Craft::t('commerce', 'Couldn’t save shipping category.'),
                'shippingCategory'
            );
        }

        return $this->asModelSuccess(
            $shippingCategory,
            Craft::t('commerce', 'Shipping category saved.'),
            'shippingCategory',
            data: [
                'id' => $shippingCategory->id,
                'name' => $shippingCategory->name,
            ]
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $ids = $this->request->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            // If it is just the one id we know it has come from an ajax request on the table
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $id) {
            if (!Plugin::getInstance()->getShippingCategories()->deleteShippingCategoryById($id)) {
                $failedIds[] = $id;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete {count, number} shipping {count, plural, one{category} other{categories}}.', [
                'count' => count($failedIds)
            ]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Shipping categories deleted.'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @since 3.2.9
     */
    public function actionSetDefaultCategory(): ?Response
    {
        $this->requirePostRequest();

        $ids = $this->request->getRequiredBodyParam('ids');
        $storeHandle = $this->request->getRequiredBodyParam('storeHandle');
        if (!$storeHandle || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            throw new InvalidConfigException('Invalid store.');
        }

        if (!empty($ids)) {
            $id = ArrayHelper::firstValue($ids);

            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id, $store->id);
            if ($shippingCategory) {
                $shippingCategory->default = true;
                if (Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
                    $this->setSuccessFlash(Craft::t('commerce', 'Shipping category updated.'));
                    return null;
                }
            }
        }

        $this->setFailFlash(Craft::t('commerce', 'Unable to set default shipping category.'));
        return null;
    }
}
