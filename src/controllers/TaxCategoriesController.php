<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Categories Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxCategoriesController extends BaseTaxSettingsController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();
        return $this->renderTemplate('commerce/tax/taxcategories/index', compact('taxCategories'));
    }

    /**
     * @param int|null $id
     * @param TaxCategory|null $taxCategory
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxCategory $taxCategory = null): Response
    {
        $variables = [
            'id' => $id,
            'taxCategory' => $taxCategory,
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes()
        ];

        if (!$variables['taxCategory']) {
            if ($variables['id']) {
                $variables['taxCategory'] = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($variables['id']);

                if (!$variables['taxCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxCategory'] = new TaxCategory();
            }
        }

        if ($variables['taxCategory']->id) {
            $variables['title'] = $variables['taxCategory']->name;
        } else {
            $variables['title'] = Plugin::t('Create a new tax category');
        }

        return $this->renderTemplate('commerce/tax/taxcategories/_edit', $variables);
    }

    /**
     * @return Response|null
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxCategory = new TaxCategory();

        // Shared attributes
        $taxCategory->id = Craft::$app->getRequest()->getBodyParam('taxCategoryId');
        $taxCategory->name = Craft::$app->getRequest()->getBodyParam('name');
        $taxCategory->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $taxCategory->description = Craft::$app->getRequest()->getBodyParam('description');
        $taxCategory->default = (bool)Craft::$app->getRequest()->getBodyParam('default');

        // Set the new product types
        $productTypes = [];
        foreach (Craft::$app->getRequest()->getBodyParam('productTypes', []) as $productTypeId) {
            if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $taxCategory->setProductTypes($productTypes);

        // Save it
        if (Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'id' => $taxCategory->id,
                    'name' => $taxCategory->name,
                ]);
            }

            Craft::$app->getSession()->setNotice(Plugin::t('Tax category saved.'));
            $this->redirectToPostedUrl($taxCategory);
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $taxCategory->getErrors()
                ]);
            }

            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save tax category.'));
        }

        // Send the tax category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'taxCategory' => $taxCategory
        ]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Plugin::t('Could not delete tax category'));
    }
}
