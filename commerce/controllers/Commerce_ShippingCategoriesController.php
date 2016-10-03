<?php
namespace Craft;

/**
 * Class Commerce_ShippingCategoriesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingCategoriesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $shippingCategories = craft()->commerce_shippingCategories->getAllShippingCategories();
        $this->renderTemplate('commerce/settings/shippingcategories/index', compact('shippingCategories'));
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
                $variables['shippingCategory'] = craft()->commerce_shippingCategories->getShippingCategoryById($id);

                if (!$variables['shippingCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingCategory'] = new Commerce_ShippingCategoryModel();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['shippingCategory']->name;
        } else {
            $variables['title'] = Craft::t('Create a new shipping category');
        }

        $this->renderTemplate('commerce/settings/shippingcategories/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingCategory = new Commerce_ShippingCategoryModel();

        // Shared attributes
        $shippingCategory->id = craft()->request->getPost('shippingCategoryId');
        $shippingCategory->name = craft()->request->getPost('name');
        $shippingCategory->handle = craft()->request->getPost('handle');
        $shippingCategory->description = craft()->request->getPost('description');
        $shippingCategory->default = craft()->request->getPost('default');

        // Save it
        if (craft()->commerce_shippingCategories->saveShippingCategory($shippingCategory)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson([
                    'success' => true,
                    'id' => $shippingCategory->id,
                    'name' => $shippingCategory->name,
                ]);
            } else {
                craft()->userSession->setNotice(Craft::t('Shipping category saved.'));
                $this->redirectToPostedUrl($shippingCategory);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson([
                    'errors' => $shippingCategory->getErrors()
                ]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t save shipping category.'));
            }
        }

        // Send the shipping category back to the template
        craft()->urlManager->setRouteVariables([
            'shippingCategory' => $shippingCategory
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        if (craft()->commerce_shippingCategories->deleteShippingCategoryById($id)) {
            $this->returnJson(['success' => true]);
        } else {
            $this->returnErrorJson(Craft::t('Could not delete shipping category'));
        }

    }

}
