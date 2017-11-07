<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Product Type Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ProductTypesController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionProductTypeIndex(): Response
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        return $this->renderTemplate('commerce/settings/producttypes/index',
            compact('productTypes'));
    }

    /**
     * @param int|null         $productTypeId
     * @param ProductType|null $productType
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEditProductType(int $productTypeId = null, ProductType $productType = null): Response
    {
        $variables = [
            'productTypeId' => $productTypeId,
            'productType' => $productType,
        ];

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
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
            }
        }

        if (!empty($variables['productTypeId'])) {
            $variables['title'] = $variables['productType']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Product Type');
        }

        return $this->renderTemplate('commerce/settings/producttypes/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSaveProductType()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $request = Craft::$app->getRequest();
        $this->requirePostRequest();

        $productType = new ProductType();

        // Shared attributes
        $productType->id = Craft::$app->getRequest()->getParam('productTypeId');
        $productType->name = Craft::$app->getRequest()->getParam('name');
        $productType->handle = Craft::$app->getRequest()->getParam('handle');
        $productType->hasDimensions = Craft::$app->getRequest()->getParam('hasDimensions');
        $productType->hasVariants = Craft::$app->getRequest()->getParam('hasVariants');
        $productType->hasVariantTitleField = $productType->hasVariants ? Craft::$app->getRequest()->getParam('hasVariantTitleField') : false;
        $productType->titleFormat = Craft::$app->getRequest()->getParam('titleFormat');
        $productType->skuFormat = Craft::$app->getRequest()->getParam('skuFormat');
        $productType->descriptionFormat = Craft::$app->getRequest()->getParam('descriptionFormat');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.'.$site->handle);

            $siteSettings = new ProductTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        $productType->setTaxCategories(Craft::$app->getRequest()->getParam('taxCategories'));
        $productType->setShippingCategories(Craft::$app->getRequest()->getParam('shippingCategories'));

        // Set the product type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $productType->getBehavior('productFieldLayout')->setFieldLayout($fieldLayout);

        // Set the variant field layout
        $variantFieldLayout = Craft::$app->getFields()->assembleLayoutFromPost('variant-layout');
        $variantFieldLayout->type = Variant::class;
        $productType->getBehavior('variantFieldLayout')->setFieldLayout($variantFieldLayout);

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

    /**
     * @return Response
     */
    public function actionDeleteProductType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $productTypeId = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getProductTypes()->deleteProductTypeById($productTypeId);
        return $this->asJson(['success' => true]);
    }
}
