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
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin;
use craft\enums\PropagationMethod;
use craft\web\assets\editsection\EditSectionAsset;
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
    public function actionProductTypeIndex(): Response
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        return $this->renderTemplate('commerce/settings/producttypes/index', compact('productTypes'));
    }

    /**
     * @param int|null $productTypeId
     * @param ProductType|null $productType
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
            $variables['title'] = Craft::t('commerce', 'Create a Product Type');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['productType'], prepend: true);

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
            ],
        ];

        $variables['tabs'] = $tabs;
        $variables['selectedTab'] = 'productTypeSettings';

        $this->getView()->registerAssetBundle(EditSectionAsset::class);

        return $this->renderTemplate('commerce/settings/producttypes/_edit', $variables);
    }

    /**
     * @throws HttpException
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionSaveProductType(): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $this->requirePostRequest();
        $productTypeId = $this->request->getBodyParam('productTypeId');

        if ($productTypeId) {
            $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId);

            if (!$productType) {
                throw new BadRequestHttpException("Invalid section ID: $productTypeId");
            }
        } else {
            $productType = new ProductType();
        }

        // Shared attributes
        $productType->id = $this->request->getBodyParam('productTypeId');
        $productType->name = $this->request->getBodyParam('name');
        $productType->handle = $this->request->getBodyParam('handle');
        $productType->enableVersioning = $this->request->getBodyParam('enableVersioning') ?? $productType->enableVersioning;
        $productType->hasDimensions = (bool)$this->request->getBodyParam('hasDimensions');
        $productType->hasProductTitleField = (bool)$this->request->getBodyParam('hasProductTitleField');
        $productType->productTitleFormat = $this->request->getBodyParam('productTitleFormat');
        $productType->productTitleTranslationMethod = $this->request->getBodyParam('productTitleTranslationMethod', $productType->productTitleTranslationMethod);
        $productType->productTitleTranslationKeyFormat = $this->request->getBodyParam('productTitleTranslationKeyFormat', $productType->productTitleTranslationKeyFormat);
        $productType->maxVariants = $this->request->getBodyParam('maxVariants') ?: null;
        $productType->hasVariantTitleField = $this->request->getBodyParam('hasVariantTitleField', false);
        $productType->variantTitleFormat = $this->request->getBodyParam('variantTitleFormat');
        $productType->variantTitleTranslationMethod = $this->request->getBodyParam('variantTitleTranslationMethod', $productType->variantTitleTranslationMethod);
        $productType->variantTitleTranslationKeyFormat = $this->request->getBodyParam('variantTitleTranslationKeyFormat', $productType->variantTitleTranslationKeyFormat);
        $productType->skuFormat = $this->request->getBodyParam('skuFormat');
        $productType->descriptionFormat = $this->request->getBodyParam('descriptionFormat');
        $productType->propagationMethod = PropagationMethod::tryFrom($this->request->getBodyParam('propagationMethod') ?? '') ?? PropagationMethod::All;
        $productType->isStructure = $this->request->getBodyParam('isStructure');
        $productType->maxLevels = $this->request->getBodyParam('maxLevels', 1);
        $productType->defaultPlacement = $this->request->getBodyParam('defaultPlacement');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $this->request->getBodyParam('sites.' . $site->handle);

            // Skip disabled sites if this is a multi-site install
            if (Craft::$app->getIsMultiSite() && empty($postedSettings['enabled'])) {
                continue;
            }

            $siteSettings = new ProductTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            $siteSettings->enabledByDefault = (bool)$postedSettings['enabledByDefault'];

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
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $productType->getBehavior('variantFieldLayout');
        $behavior->setFieldLayout($variantFieldLayout);

        // Save it
        if (Plugin::getInstance()->getProductTypes()->saveProductType($productType)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Product type saved.'));
            $this->redirectToPostedUrl($productType);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save product type.'));
        }

        // Send the productType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'productType' => $productType,
        ]);
    }

    /**
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionDeleteProductType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $productTypeId = $this->request->getRequiredBodyParam('id');

        Plugin::getInstance()->getProductTypes()->deleteProductTypeById($productTypeId);
        return $this->asSuccess();
    }
}
