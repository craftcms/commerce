<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;
use craft\commerce\helpers\VariantMatrix;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\commerce\web\assets\editproduct\EditProductAsset;
use craft\commerce\web\assets\productindex\ProductIndexAsset;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\Site;
use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Products Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductsController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requirePermission('commerce-manageProducts');
        parent::init();
    }

    /**
     * @return Response
     * @throws \yii\base\InvalidConfigException
     */
    public function actionProductIndex(): Response
    {
        $this->getView()->registerAssetBundle(ProductIndexAsset::class);
        return $this->renderTemplate('commerce/products/_index');
    }

    /**
     * @return Response
     */
    public function actionVariantIndex(): Response
    {
        return $this->renderTemplate('commerce/variants/_index');
    }

    /**
     * @param string $productTypeHandle
     * @param int|null $productId
     * @param string|null $siteHandle
     * @param Product|null $product
     * @return Response
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionEditProduct(string $productTypeHandle, int $productId = null, string $siteHandle = null, Product $product = null): Response
    {
        $variables = [
            'productTypeHandle' => $productTypeHandle,
            'productId' => $productId,
            'product' => $product
        ];

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new NotFoundHttpException('Invalid site handle: ' . $siteHandle);
            }
        }

        $this->_prepEditProductVariables($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new product');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce/products/' . $variables['productTypeHandle'] . '/{id}-{slug}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] .
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_prepVariables($variables);

        if ($variables['product']->getType()->hasVariants) {
            $variables['variantMatrixHtml'] = VariantMatrix::getVariantMatrixHtml($variables['product']);
        } else {
            $this->getView()->registerJs('Craft.Commerce.initUnlimitedStockCheckbox($("#details"));');
        }

        // Enable Live Preview?
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'], $variables['site']->id)) {
            $this->getView()->registerJs('Craft.LivePreview.init(' . Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field',
                    'extraFields' => '#details',
                    'previewUrl' => $variables['product']->getUrl(),
                    'previewAction' => Craft::$app->getSecurity()->hashData('commerce/products-preview/preview-product'),
                    'previewParams' => [
                        'typeId' => $variables['productType']->id,
                        'productId' => $variables['product']->id,
                        'siteId' => $variables['product']->siteId,
                    ]
                ]) . ');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['product']->id) {
                // If the product is enabled, use its main URL as its share URL.
                if ($variables['product']->getStatus() == Product::STATUS_LIVE) {
                    $variables['shareUrl'] = $variables['product']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('commerce/products-preview/share-product', [
                        'productId' => $variables['product']->id,
                        'siteId' => $variables['product']->siteId
                    ]);
                }
            }
        } else {
            $variables['showPreviewBtn'] = false;
        }

        $this->getView()->registerAssetBundle(EditProductAsset::class);
        return $this->renderTemplate('commerce/products/_edit', $variables);
    }

    /**
     * Deletes a product.
     *
     * @throws Exception if you try to edit a non existing Id.
     * @throws \Throwable
     */
    public function actionDeleteProduct()
    {
        $this->requirePostRequest();

        $productId = Craft::$app->getRequest()->getRequiredParam('productId');
        $product = Plugin::getInstance()->getProducts()->getProductById($productId);

        if (!$product) {
            throw new Exception(Craft::t('commerce', 'No product exists with the ID “{id}”.',
                ['id' => $productId]));
        }

        $this->enforceProductPermissions($product);

        if (!Craft::$app->getElements()->deleteElement($product)) {

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete product.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product
            ]);
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product deleted.'));
        return $this->redirectToPostedUrl($product);
    }

    /**
     * Save a new or existing product.
     *
     * @return Response|null
     * @throws Exception
     * @throws HttpException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveProduct()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $product = ProductHelper::populateProductFromPost();

        $this->enforceProductPermissions($product);

        // Save the entry (finally!)
        if ($product->enabled && $product->enabledForSite) {
            $product->setScenario(Element::SCENARIO_LIVE);
            foreach ($product->getVariants() as $variant)
            {
                $variant->setScenario(Element::SCENARIO_LIVE);
            }
        }

        if (!Craft::$app->getElements()->saveElement($product)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $product->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save product.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->getStatus(),
                'url' => $product->getUrl(),
                'cpEditUrl' => $product->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product saved.'));

        return $this->redirectToPostedUrl($product);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @param Product $product
     * @throws HttpException
     * @throws \yii\base\InvalidConfigException
     */
    protected function enforceProductPermissions(Product $product)
    {
        $this->requirePermission('commerce-manageProductType:' . $product->getType()->uid);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $variables
     * @throws \yii\base\InvalidConfigException
     */
    private function _prepVariables(array &$variables)
    {
        $variables['tabs'] = [];

        /** @var ProductType $productType */
        $productType = $variables['productType'];
        /** @var Product $product */
        $product = $variables['product'];

        foreach ($productType->getProductFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($product->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    /** @var Field $field */
                    if ($hasErrors = $product->hasErrors($field->handle . '.*')) {
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => $hasErrors ? 'error' : null
            ];
        }

        if ($productType->hasVariants) {
            $hasErrors = false;
            foreach ($product->getVariants() as $variant) {
                if ($hasErrors = $variant->hasErrors()) {
                    break;
                }
            }

            if ($product->getErrors('variants')) {
                $hasErrors = true;
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', 'Variants'),
                'url' => '#variants-container',
                'class' => $hasErrors ? 'error' : null
            ];
        }

        $sales = [];
        foreach ($product->getVariants() as $variant) {
            $variantSales = Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($variant);
            foreach ($variantSales as $sale) {
                $sales[$sale->id] = $sale;
            }
        }

        $variables['sales'] = $sales;
    }

    /**
     * @param array $variables
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    private function _prepEditProductVariables(array &$variables)
    {
        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($variables['productTypeHandle']);
        } else if (!empty($variables['productTypeId'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeById($variables['productTypeId']);
        }

        if (empty($variables['productType'])) {
            throw new NotFoundHttpException('Product Type not found');
        }

        // Get the site
        // ---------------------------------------------------------------------

        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        } else {
            $variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this product type');
        }

        if (empty($variables['site'])) {
            $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }

            $site = $variables['site'];
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($variables['productTypeHandle']);
        }

        if (empty($variables['productType'])) {
            throw new HttpException(400, craft::t('commerce', 'Wrong product type specified'));
        }

        // Get the product
        // ---------------------------------------------------------------------

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = Plugin::getInstance()->getProducts()->getProductById($variables['productId'], $variables['site']->id);

                if (!$variables['product']) {
                    throw new NotFoundHttpException('Product not found');
                }
            } else {
                $variables['product'] = new Product();
                $variables['product']->typeId = $variables['productType']->id;
                $taxCategories = $variables['productType']->getTaxCategories();
                $variables['product']->taxCategoryId = key($taxCategories);
                $shippingCategories = $variables['productType']->getShippingCategories();
                $variables['product']->shippingCategoryId = key($shippingCategories);
                $variables['product']->typeId = $variables['productType']->id;
                $variables['product']->enabled = true;
                $variables['product']->siteId = $site->id;
                $variables['product']->promotable = true;
                $variables['product']->availableForPurchase = true;
                $variables['product']->freeShipping = false;
            }
        }

        if ($variables['product']->id) {
            $this->enforceProductPermissions($variables['product']);
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['product']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }
    }
}
