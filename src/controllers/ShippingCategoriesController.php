<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use yii\base\Exception;
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
     * @return Response
     */
    public function actionIndex(): Response
    {
        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories();
        return $this->renderTemplate('commerce/shipping/shippingcategories/index', compact('shippingCategories'));
    }

    /**
     * @param int|null $id
     * @param ShippingCategory|null $shippingCategory
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, ShippingCategory $shippingCategory = null): Response
    {
        $variables = [
            'id' => $id,
            'shippingCategory' => $shippingCategory,
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
        ];

        if (!$variables['shippingCategory']) {
            if ($variables['id']) {
                $variables['shippingCategory'] = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($variables['id']);

                if (!$variables['shippingCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingCategory'] = new ShippingCategory();
            }
        }

        if ($variables['shippingCategory']->id) {
            $variables['title'] = $variables['shippingCategory']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping category');
        }

        $variables['productTypesOptions'] = [];
        if (!empty($variables['productTypes'])) {
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', function($row) {
                return ['label' => $row->name, 'value' => $row->id];
            });
        }

        $allShippingCategoryIds = ArrayHelper::getColumn(Plugin::getInstance()->getShippingCategories()->getAllShippingCategories(), 'id');
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && count($allShippingCategoryIds) === 1 && in_array($variables['id'], $allShippingCategoryIds);

        return $this->renderTemplate('commerce/shipping/shippingcategories/_edit', $variables);
    }

    /**
     * @throws HttpException
     * @noinspection Duplicates
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingCategory = new ShippingCategory();

        // Shared attributes
        $shippingCategory->id = Craft::$app->getRequest()->getBodyParam('shippingCategoryId');
        $shippingCategory->name = Craft::$app->getRequest()->getBodyParam('name');
        $shippingCategory->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $shippingCategory->description = Craft::$app->getRequest()->getBodyParam('description');
        $shippingCategory->default = (bool)Craft::$app->getRequest()->getBodyParam('default');

        // Set the new product types
        $productTypes = [];
        foreach (Craft::$app->getRequest()->getBodyParam('productTypes', []) as $productTypeId) {
            if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $shippingCategory->setProductTypes($productTypes);


        // Save it
        if (!Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $shippingCategory->getErrors(),
                ]);
            }
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save shipping category.'));

            // Send the shipping category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'shippingCategory' => $shippingCategory,
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $shippingCategory->id,
                'name' => $shippingCategory->name,
            ]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Shipping category saved.'));
        $this->redirectToPostedUrl($shippingCategory);

        // Send the shipping category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'shippingCategory' => $shippingCategory,
        ]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getShippingCategories()->deleteShippingCategoryById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not delete shipping category'));
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.2.9
     */
    public function actionSetDefaultCategory()
    {
        $this->requirePostRequest();

        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');

        if (!empty($ids)) {
            $id = ArrayHelper::firstValue($ids);

            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id);
            if ($shippingCategory) {
                $shippingCategory->default = true;
                if (Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
                    $this->setSuccessFlash(Craft::t('commerce', 'Shipping category updated.'));
                    return null;
                }
            }
        }

        $this->setFailFlash(Craft::t('commerce', 'Unable to set default shipping category.'));
    }
}
