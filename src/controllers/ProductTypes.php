<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeLocale;
use craft\commerce\Plugin;
use yii\web\HttpException;

/**
 * Class Product Type Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class ProductTypes extends BaseAdmin
{
    public function actionIndex()
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $this->renderTemplate('commerce/settings/producttypes/index',
            compact('productTypes'));
    }

    public function actionEditProductType(array $variables = [])
    {
        if (!Craft::$app->getUser()->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $variables['brandNewProductType'] = false;

        if (empty($variables['productType'])) {
            if (!empty($variables['productTypeId'])) {
                $productTypeId = $variables['productTypeId'];
                $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId);

                if (!$variables['productType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['productType'] = new ProductType();
                $variables['brandNewProductType'] = true;
            };
        }

        if (!empty($variables['productTypeId'])) {
            $variables['title'] = $variables['productType']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Product Type');
        }

        $this->renderTemplate('commerce/settings/producttypes/_edit', $variables);
    }

    public function actionSaveProductType()
    {
        if (!Craft::$app->getUser()->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $this->requirePostRequest();

        $productType = new ProductType();

        // Shared attributes
        $productType->id = Craft::$app->getRequest()->getParam('productTypeId');
        $productType->name = Craft::$app->getRequest()->getParam('name');
        $productType->handle = Craft::$app->getRequest()->getParam('handle');
        $productType->hasDimensions = Craft::$app->getRequest()->getParam('hasDimensions');
        $productType->hasUrls = Craft::$app->getRequest()->getParam('hasUrls');
        $productType->hasVariants = Craft::$app->getRequest()->getParam('hasVariants');
        $productType->hasVariantTitleField = $productType->hasVariants ? Craft::$app->getRequest()->getParam('hasVariantTitleField') : false;
        $productType->template = Craft::$app->getRequest()->getParam('template');
        $productType->titleFormat = Craft::$app->getRequest()->getParam('titleFormat');
        $productType->skuFormat = Craft::$app->getRequest()->getParam('skuFormat');
        $productType->descriptionFormat = Craft::$app->getRequest()->getParam('descriptionFormat');

        $locales = [];

        foreach (craft()->i18n->getSiteLocaleIds() as $localeId) {
            $locales[$localeId] = new ProductTypeLocale([
                'locale' => $localeId,
                'urlFormat' => Craft::$app->getRequest()->getParam('urlFormat.'.$localeId)
            ]);
        }

        $productType->setLocales($locales);

        $productType->setTaxCategories(Craft::$app->getRequest()->getParam('taxCategories'));
        $productType->setShippingCategories(Craft::$app->getRequest()->getParam('shippingCategories'));

        // Set the product type field layout
        $fieldLayout = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Commerce_Product';
        $productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

        // Set the variant field layout
        $variantFieldLayout = craft()->fields->assembleLayoutFromPost('variant-layout');
        $variantFieldLayout->type = 'Commerce_Variant';
        $productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

        // Save it
        if (Plugin::getInstance()->getProductTypes()->saveProductType($productType)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product type saved.'));
            $this->redirectToPostedUrl($productType);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save product type.'));
        }

        // Send the productType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'productType' => $productType
        ]);
    }


    public function actionDeleteProductType()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $productTypeId = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getProductTypes()->deleteProductTypeById($productTypeId);
        $this->asJson(['success' => true]);
    }
}
