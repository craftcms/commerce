<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use yii\base\Exception;
use yii\base\InvalidConfigException;
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
     * @param string|null $storeHandle
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();
        return $this->renderTemplate('commerce/store-management/tax/taxcategories/index', compact('taxCategories', 'store'));
    }

    /**
     * @param int|null $id
     * @param TaxCategory|null $taxCategory
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, TaxCategory $taxCategory = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables = [
            'id' => $id,
            'taxCategory' => $taxCategory,
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
            'store' => $store,
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
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', function($row) {
                return ['label' => $row->name, 'value' => $row->id];
            });
        }

        $allTaxCategoryIds = array_keys(Plugin::getInstance()->getTaxCategories()->getAllTaxCategories());
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && count($allTaxCategoryIds) === 1 && in_array($variables['id'], $allTaxCategoryIds);

        // Get all tax rates for all stores
        $taxRates = collect();
        Plugin::getInstance()->getStores()->getAllStores()->each(fn(Store $s) => $taxRates->push(...Plugin::getInstance()->getTaxRates()->getAllTaxRates($s->id)->all()));
        $variables['taxRates'] = $taxRates;

        return $this->renderTemplate('commerce/store-management/tax/taxcategories/_edit', $variables);
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
            if (!Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($id)) {
                $failedIds[] = $id;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete {count, number} tax {count, plural, one{category} other{categories}}.', [
                'count' => count($failedIds)
            ]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Tax categories deleted.'));
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
