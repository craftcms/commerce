<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin;
use Throwable;
use yii\web\BadRequestHttpException;
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
        $variables = compact('productTypeId', 'productType');

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
            $variables['title'] = Plugin::t('Create a Product Type');
        }

        $tabs = [
            'productTypeSettings' => [
                'label' => Plugin::t('Settings'),
                'url' => '#product-type-settings',
            ],
            'taxAndShipping' => [
                'label' => Plugin::t('Tax & Shipping'),
                'url' => '#tax-and-shipping',
            ],
            'productFields' => [
                'label' => Plugin::t('Product Fields'),
                'url' => '#product-fields',
            ],
            'variantFields' => [
                'label' => Plugin::t('Variant Fields'),
                'url' => '#variant-fields',
            ]
        ];

        $variables['tabs'] = $tabs;
        $variables['selectedTab'] = 'productTypeSettings';

        return $this->renderTemplate('commerce/settings/producttypes/_edit', $variables);
    }

    /**
     * @throws HttpException
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionSaveProductType()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
            throw new HttpException(403, Plugin::t('This action is not allowed for the current user.'));
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
        $productType->titleLabel = Craft::$app->getRequest()->getBodyParam('titleLabel', $productType->titleLabel);
        $productType->variantTitleLabel = Craft::$app->getRequest()->getBodyParam('variantTitleLabel', $productType->variantTitleLabel);
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
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $productType->getBehavior('productFieldLayout');
        $behavior->setFieldLayout($fieldLayout);

        // Set the variant field layout
        $variantFieldLayout = Craft::$app->getFields()->assembleLayoutFromPost('variant-layout');
        $variantFieldLayout->type = Variant::class;
        $behavior = $productType->getBehavior('variantFieldLayout');
        $behavior->setFieldLayout($variantFieldLayout);

        // Save it
        if (Plugin::getInstance()->getProductTypes()->saveProductType($productType)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Product type saved.'));
            $this->redirectToPostedUrl($productType);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save product type.'));
        }

        // Send the productType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'productType' => $productType
        ]);
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws BadRequestHttpException
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
