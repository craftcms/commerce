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
    public function actionIndex(): Response
    {
        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories();
        return $this->renderTemplate('commerce/shipping/shippingcategories/index', compact('shippingCategories'));
    }

    /**
     * @param int|null $id
     * @param ShippingCategory|null $shippingCategory
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

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingCategory'], prepend: true);

        $variables['productTypesOptions'] = [];
        if (!empty($variables['productTypes'])) {
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', fn($row) => ['label' => $row->name, 'value' => $row->id]);
        }

        $allShippingCategoryIds = ArrayHelper::getColumn(Plugin::getInstance()->getShippingCategories()->getAllShippingCategories(), 'id');
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && count($allShippingCategoryIds) === 1 && in_array($variables['id'], $allShippingCategoryIds);

        return $this->renderTemplate('commerce/shipping/shippingcategories/_edit', $variables);
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
            // @TODO: re-word this message at next translations update
            return $this->asFailure(Craft::t('commerce', 'Could not delete shipping category'));
        }

        // @TODO: re-word add better message in next translation update
        return $this->asSuccess();
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
        return null;
    }
}
