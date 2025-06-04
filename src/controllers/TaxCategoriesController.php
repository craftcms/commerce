<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
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
    public function actionIndex(): Response
    {
        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();
        return $this->renderTemplate('commerce/tax/taxcategories/index', compact('taxCategories'));
    }

    /**
     * @param int|null $id
     * @param TaxCategory|null $taxCategory
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

        DebugPanel::prependOrAppendModelTab(model: $variables['taxCategory'], prepend: true);

        $variables['productTypesOptions'] = [];
        if (!empty($variables['productTypes'])) {
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', fn($row) => ['label' => $row->name, 'value' => $row->id]);
        }

        $allTaxCategoryIds = array_keys(Plugin::getInstance()->getTaxCategories()->getAllTaxCategories());
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && count($allTaxCategoryIds) === 1 && in_array($variables['id'], $allTaxCategoryIds);

        return $this->renderTemplate('commerce/tax/taxcategories/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @noinspection Duplicates
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $taxCategory = new TaxCategory();

        // Shared attributes
        $taxCategory->id = $this->request->getBodyParam('taxCategoryId');
        $taxCategory->name = $this->request->getBodyParam('name');
        $taxCategory->handle = $this->request->getBodyParam('handle');
        $taxCategory->description = $this->request->getBodyParam('description');
        $taxCategory->default = (bool)$this->request->getBodyParam('default');

        // Set the new product types
        $postedProductTypes = $this->request->getBodyParam('productTypes', []) ?: [];
        $productTypes = [];
        foreach ($postedProductTypes as $productTypeId) {
            if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $taxCategory->setProductTypes($productTypes);

        // Save it
        if (!Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
            return $this->asModelFailure(
                $taxCategory,
                Craft::t('commerce', 'Couldn’t save tax category.'),
                'taxCategory'
            );
        }

        return $this->asModelSuccess(
            $taxCategory,
            Craft::t('commerce', 'Tax category saved.'),
            'taxCategory'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (!Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete tax category'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.2.9
     */
    public function actionSetDefaultCategory(): ?Response
    {
        $this->requirePostRequest();

        $ids = $this->request->getRequiredBodyParam('ids');

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
        return null;
    }
}
