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
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', function($row) {
                return ['label' => $row->name, 'value' => $row->id];
            });
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
        $productTypes = [];
        foreach ($this->request->getBodyParam('productTypes', []) as $productTypeId) {
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
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (!Plugin::getInstance()->getShippingCategories()->deleteShippingCategoryById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete shipping category'));
        }

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
