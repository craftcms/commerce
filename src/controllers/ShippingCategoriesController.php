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
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes()
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
            $variables['title'] = Plugin::t('Create a new shipping category');
        }

        return $this->renderTemplate('commerce/shipping/shippingcategories/_edit', $variables);
    }

    /**
     * @throws HttpException
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
                    'errors' => $shippingCategory->getErrors()
                ]);
            }
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save shipping category.'));

            // Send the shipping category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'shippingCategory' => $shippingCategory
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

        Craft::$app->getSession()->setNotice(Plugin::t('Shipping category saved.'));
        $this->redirectToPostedUrl($shippingCategory);

        // Send the shipping category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'shippingCategory' => $shippingCategory
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

        return $this->asErrorJson(Plugin::t('Could not delete shipping category'));
    }
}
