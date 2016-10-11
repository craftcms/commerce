<?php
namespace Craft;

/**
 * Class Commerce_ProductTypesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ProductTypesController extends Commerce_BaseAdminController
{
    public function actionIndex()
    {
        $productTypes = craft()->commerce_productTypes->getAllProductTypes();
        $this->renderTemplate('commerce/settings/producttypes/index',
            compact('productTypes'));
    }

    public function actionEditProductType(array $variables = [])
    {
        if (!craft()->userSession->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
        }

        $variables['brandNewProductType'] = false;

        if (empty($variables['productType'])) {
            if (!empty($variables['productTypeId'])) {
                $productTypeId = $variables['productTypeId'];
                $variables['productType'] = craft()->commerce_productTypes->getProductTypeById($productTypeId);

                if (!$variables['productType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['productType'] = new Commerce_ProductTypeModel();
                $variables['brandNewProductType'] = true;
            };
        }

        if (!empty($variables['productTypeId'])) {
            $variables['title'] = $variables['productType']->name;
        } else {
            $variables['title'] = Craft::t('Create a Product Type');
        }

        $this->renderTemplate('commerce/settings/producttypes/_edit', $variables);
    }

    public function actionSaveProductType()
    {
        if (!craft()->userSession->getUser()->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
        }

        $this->requirePostRequest();

        $productType = new Commerce_ProductTypeModel();

        // Shared attributes
        $productType->id = craft()->request->getPost('productTypeId');
        $productType->name = craft()->request->getPost('name');
        $productType->handle = craft()->request->getPost('handle');
        $productType->hasDimensions = craft()->request->getPost('hasDimensions');
        $productType->hasUrls = craft()->request->getPost('hasUrls');
        $productType->hasVariants = craft()->request->getPost('hasVariants');
        $productType->hasVariantTitleField = $productType->hasVariants ? craft()->request->getPost('hasVariantTitleField') : false;
        $productType->template = craft()->request->getPost('template');
        $productType->titleFormat = craft()->request->getPost('titleFormat');
        $productType->skuFormat = craft()->request->getPost('skuFormat');
        $productType->descriptionFormat = craft()->request->getPost('descriptionFormat');

        $locales = [];

        foreach (craft()->i18n->getSiteLocaleIds() as $localeId) {
            $locales[$localeId] = new Commerce_ProductTypeLocaleModel([
                'locale' => $localeId,
                'urlFormat' => craft()->request->getPost('urlFormat.' . $localeId)
            ]);
        }

        $productType->setLocales($locales);

        $productType->setTaxCategories(craft()->request->getPost('taxCategories'));
        $productType->setShippingCategories(craft()->request->getPost('shippingCategories'));

        // Set the product type field layout
        $fieldLayout = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Commerce_Product';
        $productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

        // Set the variant field layout
        $variantFieldLayout = craft()->fields->assembleLayoutFromPost('variant-layout');
        $variantFieldLayout->type = 'Commerce_Variant';
        $productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

        // Save it
        if (craft()->commerce_productTypes->saveProductType($productType)) {
            craft()->userSession->setNotice(Craft::t('Product type saved.'));
            $this->redirectToPostedUrl($productType);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save product type.'));
        }

        // Send the productType back to the template
        craft()->urlManager->setRouteVariables([
            'productType' => $productType
        ]);
    }


    public function actionDeleteProductType()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $productTypeId = craft()->request->getRequiredPost('id');

        craft()->commerce_productTypes->deleteProductTypeById($productTypeId);
        $this->returnJson(['success' => true]);
    }
}
