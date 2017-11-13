<?php

namespace craft\commerce\controllers;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;
use craft\commerce\helpers\VariantMatrix;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\commerce\web\assets\editproduct\EditProductAsset;
use craft\helpers\DateTimeHelper;
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
 * @since  2.0
 */
class ProductsController extends BaseCpController
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['actionViewSharedProduct'];

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
     */
    public function actionProductIndex(): Response
    {
        return $this->renderTemplate('commerce/products/_index');
    }

    /**
     * @param string       $productTypeHandle
     * @param int|null     $productId
     * @param string|null  $siteHandle
     * @param Product|null $product
     *
     * @return Response
     * @throws NotFoundHttpException
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
                throw new NotFoundHttpException('Invalid site handle: '.$siteHandle);
            }
        }

        $this->_prepProductVariables($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new product');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce/products/'.$variables['productTypeHandle'].'/{id}-{slug}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'].
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/'.$variables['site']->handle : '');

        $this->_prepVariables($variables);

        if ($variables['product']->getType()->hasVariants) {
            $variables['variantMatrixHtml'] = VariantMatrix::getVariantMatrixHtml($variables['product']);
        } else {
            $this->getView()->registerJs('Craft.Commerce.initUnlimitedStockCheckbox($("#details"));');
        }

        // Enable Live Preview?
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'])) {
            $this->getView()->registerJs('Craft.LivePreview.init('.Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field',
                    'extraFields' => '#meta-pane, #variants',
                    'previewUrl' => $variables['product']->getUrl(),
                    'previewAction' => 'commerce/products/previewProduct',
                    'previewParams' => [
                        'typeId' => $variables['productType']->id,
                        'productId' => $variables['product']->id,
                        'site' => $variables['product']->site,
                    ]
                ]).');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['product']->id) {
                // If the product is enabled, use its main URL as its share URL.
                if ($variables['product']->getStatus() == Product::STATUS_LIVE) {
                    $variables['shareUrl'] = $variables['product']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('commerce/products/shareProduct', [
                        'productId' => $variables['product']->id,
                        'site' => $variables['product']->site
                    ]);
                }
            }
        } else {
            $variables['showPreviewBtn'] = false;
        }

        $variables['promotions']['sales'] = Plugin::getInstance()->getSales()->getSalesForProduct($variables['product']);

        $this->getView()->registerAssetBundle(EditProductAsset::class);
        return $this->renderTemplate('commerce/products/_edit', $variables);
    }

    /**
     * Previews a product.
     *
     * @throws HttpException
     */
    public function actionPreviewProduct()
    {
        $this->requirePostRequest();

        $product = $this->_setProductFromPost();

        $this->enforceProductPermissions($product);

        $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param mixed $productId
     * @param mixed $site
     *
     * @return Response
     * @throws HttpException
     */
    public function actionShareProduct($productId, $site = null): Response
    {
        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $site);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->enforceProductPermissions($product);

        // Make sure the product actually can be viewed
        if (!Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($product->getType())) {
            throw new HttpException(404);
        }

        // Create the token and redirect to the product URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'action' => 'commerce/products/viewSharedProduct',
            'params' => ['productId' => $productId, 'site' => $product->site]
        ]);

        $url = UrlHelper::urlWithToken($product->getUrl(), $token);

        return $this->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param mixed $productId
     * @param mixed $site
     *
     * @throws HttpException
     * @return void
     */
    public function actionViewSharedProduct($productId, $site = null)
    {
        $this->requireToken();

        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $site);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->_showProduct($product);

        return null;
    }

    /**
     * Deletes a product.
     *
     * @throws Exception if you try to edit a non existing Id.
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

        if (Craft::$app->getElements()->deleteElement($product)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product deleted.'));
                return $this->redirectToPostedUrl($product);
            }
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete product.'));

                Craft::$app->getUrlManager()->setRouteParams([
                    'product' => $product

                ]);
            }
        }
    }

    /**
     * Save a new or existing product.
     */
    public function actionSaveProduct()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $product = $this->_setProductFromPost();

        $variants = $request->getParam('variants');
        $newVariants = [];
        foreach ($variants as $key => $variant) {
            $newVariants[] = ProductHelper::populateProductVariantModel($product, $variant, $key);
        }
        $product->setVariants($newVariants);

        $this->enforceProductPermissions($product);

        if (!Craft::$app->getElements()->saveElement($product)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $product->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save product.'));

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

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Product saved.'));

        return $this->redirectToPostedUrl($product);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @param Product $product
     *
     * @throws HttpException
     */
    protected function enforceProductPermissions(Product $product)
    {
        if (!$product->getType()) {
            Craft::error('Attempting to access a product that doesn’t have a type', __METHOD__);
            throw new HttpException(404);
        }

        $this->requirePermission('commerce-manageProductType:'.$product->getType()->id);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $variables
     *
     * @throws HttpException
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
                    if ($hasErrors = $product->hasErrors($field->handle)) {
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab'.($index + 1),
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

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', 'Variants'),
                'url' => '#variants',
                'class' => $hasErrors ? 'error' : null
            ];
        }

        $variables['primaryVariant'] = $product->getVariants()[0];
    }

    /**
     * @param $variables
     *
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    private function _prepProductVariables(&$variables)
    {
        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($variables['productTypeHandle']);
        } else if (!empty($variables['productTypeId'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeById($variables['productTypeId']);
        }

        if (empty($variables['productType'])) {
            throw new NotFoundHttpException('Section not found');
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
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this section');
        }

        if (empty($variables['site'])) {
            $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }
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
            throw new HttpException(400,
                craft::t('commerce', 'Wrong product type specified'));
        }

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = Plugin::getInstance()->getProducts()->getProductById($variables['productId'], $variables['site']->id);

                if (!$variables['product']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['product'] = new Product();
                $variables['product']->typeId = $variables['productType']->id;
                $taxCategories = $variables['productType']->getTaxCategories();
                $variables['product']->taxCategoryId = key($taxCategories);
                $shippingCategories = $variables['productType']->getShippingCategories();
                $variables['product']->shippingCategoryId = key($shippingCategories);
                $variables['product']->typeId = $variables['productType']->id;
                if ($variables['siteId']) {
                    $variables['product']->site = $variables['siteId'];
                }
            }
        }

        if (!empty($variables['product']->id)) {
            $this->enforceProductPermissions($variables['product']);
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['product']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }
    }

    /**
     * @return Product
     * @throws Exception
     */
    private function _setProductFromPost(): Product
    {
        $request = Craft::$app->getRequest();
        $productId = $request->getParam('productId');
        $site = $request->getParam('site');

        if ($productId) {
            $product = Plugin::getInstance()->getProducts()->getProductById($productId, $site);

            if (!$product) {
                throw new Exception(Craft::t('commerce', 'No product with the ID “{id}”',
                    ['id' => $productId]));
            }
        } else {
            $product = new Product();
        }

        $data['typeId'] = $request->getBodyParam('typeId');
        $data['enabled'] = $request->getBodyParam('enabled');
        $data['postDate'] = (($date = $request->getParam('postDate')) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $data['postDate']);
        $data['expiryDate'] = (($date = $request->getParam('expiryDate')) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $data['expiryDate']);
        $data['promotable'] = $request->getBodyParam('promotable');
        $data['freeShipping'] = $request->getBodyParam('freeShipping');
        $data['taxCategoryId'] = $request->getBodyParam('taxCategoryId');
        $data['shippingCategoryId'] = $request->getBodyParam('shippingCategoryId');
        $data['slug'] = $request->getBodyParam('slug');

        ProductHelper::populateProductModel($product, $data);

        $product->enabledForSite = (bool)$request->getParam('enabledForSite', $product->enabledForSite);
        $product->title = $request->getParam('title', $product->title);
        $product->setFieldValuesFromRequest('fields');

        return $product;
    }

    /**
     * Displays a product.
     *
     * @param Product $product
     *
     * @throws HttpException
     * @return Response
     */
    private function _showProduct(Product $product): Response
    {
        $productType = $product->getType();

        if (!$productType) {
            Craft::error('Attempting to preview a product that doesn’t have a type', __METHOD__);
            throw new HttpException(404);
        }

        Craft::$app->language = $product->site;

        // Have this product override any freshly queried products with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($product);

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($productType->template, [
            'product' => $product
        ]);
    }
}
