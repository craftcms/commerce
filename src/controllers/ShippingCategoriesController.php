<?php

namespace craft\commerce\controllers;

use craft\commerce\models\ShippingCategory;
use Craft;
use craft\commerce\Plugin;

/**
 * Class Shipping Categories Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class ShippingCategoriesController extends BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories();
        return $this->renderTemplate('commerce/settings/shippingcategories/index', compact('shippingCategories'));
    }

    /**
     * Create/Edit Shipping Category
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['shippingCategory'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['shippingCategory'] = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id);

                if (!$variables['shippingCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingCategory'] = new ShippingCategory();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['shippingCategory']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping category');
        }

        return $this->renderTemplate('commerce/settings/shippingcategories/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingCategory = new ShippingCategory();

        // Shared attributes
        $shippingCategory->id = Craft::$app->getRequest()->getParam('shippingCategoryId');
        $shippingCategory->name = Craft::$app->getRequest()->getParam('name');
        $shippingCategory->handle = Craft::$app->getRequest()->getParam('handle');
        $shippingCategory->description = Craft::$app->getRequest()->getParam('description');
        $shippingCategory->default = Craft::$app->getRequest()->getParam('default');

        // Save it
        if (Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson([
                    'success' => true,
                    'id' => $shippingCategory->id,
                    'name' => $shippingCategory->name,
                ]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Shipping category saved.'));
                $this->redirectToPostedUrl($shippingCategory);
            }
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson([
                    'errors' => $shippingCategory->getErrors()
                ]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save shipping category.'));
            }
        }

        // Send the shipping category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'shippingCategory' => $shippingCategory
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        if (Plugin::getInstance()->getShippingCategories()->deleteShippingCategoryById($id)) {
            $this->asJson(['success' => true]);
        } else {
            $this->asErrorJson(Craft::t('commerce', 'Could not delete shipping category'));
        }
    }

}
