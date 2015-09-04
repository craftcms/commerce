<?php
namespace Craft;

/**
 * Class Market_ProductTypeController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_ProductTypeController extends Market_BaseController
{
    protected $allowAnonymous = false;

    public function actionIndex()
    {
        $this->requireAdmin();

        $productTypes = craft()->market_productType->getAll();
        $this->renderTemplate('market/settings/producttypes/index',
            compact('productTypes'));

    }

    public function actionEditProductType(array $variables = [])
    {
        $this->requireAdmin();

        $variables['brandNewProductType'] = false;

        if (empty($variables['productType'])) {
            if (!empty($variables['productTypeId'])) {
                $productTypeId            = $variables['productTypeId'];
                $variables['productType'] = craft()->market_productType->getById($productTypeId);

                if (!$variables['productType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['productType']         = new Market_ProductTypeModel();
                $variables['brandNewProductType'] = true;
            };
        }

        if (!empty($variables['productTypeId'])) {
            $variables['title'] = $variables['productType']->name;
        } else {
            $variables['title'] = Craft::t('Create a Product Type');
        }

        $this->renderTemplate('market/settings/producttypes/_edit', $variables);
    }

    public function actionSaveProductType()
    {
        $this->requireAdmin();

        $this->requirePostRequest();

        $productType = new Market_ProductTypeModel();

        // Shared attributes
        $productType->id          = craft()->request->getPost('productTypeId');
        $productType->name        = craft()->request->getPost('name');
        $productType->handle      = craft()->request->getPost('handle');
        $productType->hasUrls     = craft()->request->getPost('hasUrls');
        $productType->hasVariants = craft()->request->getPost('hasVariants');
        $productType->template    = craft()->request->getPost('template');
        $productType->urlFormat   = craft()->request->getPost('urlFormat');
        $productType->titleFormat   = craft()->request->getPost('titleFormat');

        $locales = [];

        foreach (craft()->i18n->getSiteLocaleIds() as $localeId)
        {
            $locales[$localeId] = new Market_ProductTypeLocaleModel(array(
                'locale'          => $localeId,
                'urlFormat'       => craft()->request->getPost('urlFormat.'.$localeId),
                'nestedUrlFormat' => craft()->request->getPost('nestedUrlFormat.'.$localeId),
            ));
        }

        $productType->setLocales($locales);

        // Set the field layout
        $fieldLayout       = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Market_Product';
        $productType->asa('productFieldLayout')->setFieldLayout($fieldLayout);

        // Set the variant field layout, we need to manually do so since assembleLayout has hardcoded post names
        $postedFieldLayout        = craft()->request->getPost('variantfieldLayout', []);
        $requiredFields           = craft()->request->getPost('variantrequiredFields', []);
        $variantFieldLayout       = craft()->fields->assembleLayout($postedFieldLayout, $requiredFields);

        $variantFieldLayout->type = 'Market_Variant';
        $productType->asa('variantFieldLayout')->setFieldLayout($variantFieldLayout);

        // Save it
        if (craft()->market_productType->save($productType)) {
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
        $this->requireAdmin();
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $productTypeId = craft()->request->getRequiredPost('id');

        craft()->market_productType->deleteById($productTypeId);
        $this->returnJson(['success' => true]);
    }
} 