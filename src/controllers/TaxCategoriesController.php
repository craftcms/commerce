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
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
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
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
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
            $variables['title'] = Craft::t('commerce', 'Create a new tax category');
        }

        $variables['productTypesOptions'] = [];
        if (!empty($variables['productTypes'])) {
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', function($row) {
                return ['label' => $row->name, 'value' => $row->id];
            });
        }

        $allTaxCategoryIds = array_keys(Plugin::getInstance()->getTaxCategories()->getAllTaxCategories());
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && count($allTaxCategoryIds) === 1 && in_array($variables['id'], $allTaxCategoryIds);

        return $this->renderTemplate('commerce/tax/taxcategories/_edit', $variables);
    }

    /**
     * @return Response|null
     * @throws HttpException
     * @noinspection Duplicates
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

            $this->setSuccessFlash(Craft::t('commerce', 'Tax category saved.'));
            $this->redirectToPostedUrl($taxCategory);
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $taxCategory->getErrors(),
                ]);
            }

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save tax category.'));
        }

        // Send the tax category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'taxCategory' => $taxCategory,
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

        return $this->asErrorJson(Craft::t('commerce', 'Could not delete tax category'));
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

            $taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);
            if ($taxCategory) {
                $taxCategory->default = true;
                if (Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
                    $this->setSuccessFlash(Craft::t('commerce', 'Tax category updated.'));
                    return null;
                }
            }
        }

        $this->setFailFlash(Craft::t('commerce', 'Unable to set default tax category.'));
    }
}
