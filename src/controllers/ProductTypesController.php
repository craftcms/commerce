<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
 * @since 2.0
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
        return $this->renderTemplate('commerce/settings/producttypes/index', compact('productTypes'));
    }

    /**
     * @param int|null $productTypeId
     * @param ProductType|null $productType
     * @return Response
     * @throws HttpException
     */
    public function actionEditProductType(int $productTypeId = null, ProductType $productType = null): Response
    {
        $variables = [
            'productTypeId' => $productTypeId,
            'productType' => $productType,
        ];

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

        $tabs = [
            'productTypeSettings' => [
                'label' => Craft::t('commerce', 'Settings'),
                'url' => '#product-type-settings',
            ],
            'taxAndShipping' => [
                'label' => Craft::t('commerce', 'Tax & Shipping'),
                'url' => '#tax-and-shipping',
            ],
            'productFields' => [
                'label' => Craft::t('commerce', 'Product Fields'),
                'url' => '#product-fields',
            ],
            'variantFields' => [
                'label' => Craft::t('commerce', 'Variant Fields'),
                'url' => '#variant-fields',
            ]
        ];

        $variables['tabs'] = $tabs;
        $variables['selectedTab'] = 'productTypeSettings';

        return $this->renderTemplate('commerce/settings/producttypes/_edit', $variables);
    }

    /**
     * @throws HttpException
     * @throws \Throwable
     * @throws \yii\web\BadRequestHttpException
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
        $productType->id = Craft::$app->getRequest()->getBodyParam('productTypeId');
        $productType->name = Craft::$app->getRequest()->getBodyParam('name');
        $productType->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $productType->hasDimensions = (bool)Craft::$app->getRequest()->getBodyParam('hasDimensions');
        $productType->hasVariants = (bool)Craft::$app->getRequest()->getBodyParam('hasVariants');
        $productType->hasVariantTitleField = (bool)$productType->hasVariants ? (bool)Craft::$app->getRequest()->getBodyParam('hasVariantTitleField') : false;
        $productType->titleFormat = Craft::$app->getRequest()->getBodyParam('titleFormat');
        $productType->skuFormat = Craft::$app->getRequest()->getBodyParam('skuFormat');
        $productType->descriptionFormat = Craft::$app->getRequest()->getBodyParam('descriptionFormat');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.' . $site->handle);

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
     * @throws \Throwable
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteProductType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $productTypeId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getProductTypes()->deleteProductTypeById($productTypeId);
        return $this->asJson(['success' => true]);
    }
}
