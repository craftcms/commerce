<?php
namespace craft\commerce\controllers;

use craft\commerce\models\TaxCategory;

/**
 * Class Tax Categories Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class TaxCategories extends BaseAdmin
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();
        $this->renderTemplate('commerce/settings/taxcategories/index', compact('taxCategories'));
    }

    /**
     * Create/Edit Tax Category
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['taxCategory'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxCategory'] = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);

                if (!$variables['taxCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxCategory'] = new TaxCategory();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxCategory']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new tax category');
        }

        $this->renderTemplate('commerce/settings/taxcategories/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxCategory = new TaxCategory();

        // Shared attributes
        $taxCategory->id = Craft::$app->getRequest()->getParam('taxCategoryId');
        $taxCategory->name = Craft::$app->getRequest()->getParam('name');
        $taxCategory->handle = Craft::$app->getRequest()->getParam('handle');
        $taxCategory->description = Craft::$app->getRequest()->getParam('description');
        $taxCategory->default = Craft::$app->getRequest()->getParam('default');

        // Save it
        if (Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asJson([
                    'success' => true,
                    'id' => $taxCategory->id,
                    'name' => $taxCategory->name,
                ]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Tax category saved.'));
                $this->redirectToPostedUrl($taxCategory);
            }
        } else {
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asJson([
                    'errors' => $taxCategory->getErrors()
                ]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save tax category.'));
            }
        }

        // Send the tax category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'taxCategory' => $taxCategory
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

        if (Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($id)) {
            $this->asJson(['success' => true]);
        } else {
            $this->asErrorJson(Craft::t('commerce', 'Could not delete tax category'));
        }
    }

}
