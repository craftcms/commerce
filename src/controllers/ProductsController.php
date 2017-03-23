<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Db;
use craft\commerce\helpers\Product as ProductHelper;
use craft\commerce\helpers\VariantMatrix;
use craft\commerce\Plugin;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use yii\web\HttpException;

/**
 * Class Products Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class ProductsController extends BaseCpController
{

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['actionViewSharedProduct'];

    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-manageProducts');
        parent::init();
    }


    /**
     * Index of products
     *
     * @param array
     */
    public function actionProductIndex(array $variables = [])
    {
        $this->renderTemplate('commerce/products/_index', $variables);
    }

    /**
     * Prepare screen to edit a product.
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEditProduct(array $variables = [])
    {
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
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id != $site->id ? '/'.$site->handle : '');

        $this->_prepVariables($variables);

        if ($variables['product']->getType()->hasVariants) {
            $variables['variantMatrixHtml'] = VariantMatrix::getVariantMatrixHtml($variables['product']);
        } else {
            Craft::$app->getView()->includeJs('Craft.Commerce.initUnlimitedStockCheckbox($("#meta-pane"));');
        }

        // Enable Live Preview?
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'])) {
            Craft::$app->getView()->includeJs('Craft.LivePreview.init('.Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field',
                    'extraFields' => '#meta-pane, #variants-pane',
                    'previewUrl' => $variables['product']->getUrl(),
                    'previewAction' => 'commerce/products/previewProduct',
                    'previewParams' => [
                        'typeId' => $variables['productType']->id,
                        'productId' => $variables['product']->id,
                        'locale' => $variables['product']->locale,
                    ]
                ]).');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['product']->id) {
                // If the product is enabled, use its main URL as its share URL.
                if ($variables['product']->getStatus() == Product::LIVE) {
                    $variables['shareUrl'] = $variables['product']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('commerce/products/shareProduct', [
                        'productId' => $variables['product']->id,
                        'locale' => $variables['product']->locale
                    ]);
                }
            }
        } else {
            $variables['showPreviewBtn'] = false;
        }

        $variables['promotions']['sales'] = Plugin::getInstance()->getSales()->getSalesForProduct($variables['product']);

        Craft::$app->getView()->includeCssResource('commerce/product.css');
        $this->renderTemplate('commerce/products/_edit', $variables);
    }

    private function _prepProductVariables(&$variables)
    {
        $variables['localeIds'] = Craft::$app->getI18n()->getEditableLocaleIds();

        if (!$variables['localeIds']) {
            throw new HttpException(403, Craft::t('commerce', 'Your account doesn’t have permission to edit any of this site’s locales.'));
        }

        if (empty($variables['localeId'])) {
            $variables['localeId'] = Craft::$app->language;

            if (!in_array($variables['localeId'], $variables['localeIds'])) {
                $variables['localeId'] = $variables['localeIds'][0];
            }
        } else {
            // Make sure they were requesting a valid locale
            if (!in_array($variables['localeId'], $variables['localeIds'])) {
                throw new HttpException(404);
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
                $variables['product'] = Plugin::getInstance()->getProducts()->getProductById($variables['productId'], $variables['localeId']);

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
                if ($variables['localeId']) {
                    $variables['product']->locale = $variables['localeId'];
                }
            }
        }

        if (!empty($variables['product']->id)) {
            $this->enforceProductPermissions($variables['product']);
            $variables['enabledLocales'] = Craft::$app->getElements()->getEnabledLocalesForElement($variables['product']->id);
        } else {
            $variables['enabledLocales'] = [];

            foreach (Craft::$app->getI18n()->getEditableLocaleIds() as $locale) {
                $variables['enabledLocales'][] = $locale;
            }
        }
    }

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

    /**
     * @param $variables
     *
     * @throws HttpException
     */
    private function _prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        foreach ($variables['productType']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($variables['product']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($variables['product']->getErrors($field->getField()->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab'.($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }

        $variables['primaryVariant'] = reset($variables['product']->getVariants());
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
     * @return Product
     * @throws Exception
     */
    private function _setProductFromPost()
    {
        $productId = Craft::$app->getRequest()->getParam('productId');
        $locale = Craft::$app->getRequest()->getParam('locale');

        if ($productId) {
            $product = Plugin::getInstance()->getProducts()->getProductById($productId, $locale);

            if (!$product) {
                throw new Exception(Craft::t('commerce', 'No product with the ID “{id}”',
                    ['id' => $productId]));
            }
        } else {
            $product = new Product();
        }

        ProductHelper::populateProductModel($product, Craft::$app->getRequest()->getParams());

        $product->localeEnabled = (bool)Craft::$app->getRequest()->getParam('localeEnabled', $product->localeEnabled);
        $product->getContent()->title = Craft::$app->getRequest()->getParam('title', $product->title);
        $product->setContentFromPost('fields');

        return $product;
    }

    /**
     * Displays a product.
     *
     * @param Product $product
     *
     * @throws HttpException
     * @return null
     */
    private function _showProduct(Product $product)
    {
        $productType = $product->getType();

        if (!$productType) {
            Craft::error('Attempting to preview a product that doesn’t have a type', __METHOD__);
            throw new HttpException(404);
        }

        Craft::$app->language = $product->locale;

        // Have this product override any freshly queried products with the same ID/locale
        Craft::$app->getElements()->setPlaceholderElement($product);

        Craft::$app->getView()->getTwig()->disableStrictVariables();

        $this->renderTemplate($productType->template, [
            'product' => $product
        ]);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param mixed $productId
     * @param mixed $locale
     *
     * @throws HttpException
     */
    public function actionShareProduct($productId, $locale = null)
    {
        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $locale);

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
            'params' => ['productId' => $productId, 'locale' => $product->locale]
        ]);

        $url = UrlHelper::getUrlWithToken($product->getUrl(), $token);
        Craft::$app->getRequest()->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param mixed $productId
     * @param mixed $locale
     *
     * @throws HttpException
     * @return null
     */
    public function actionViewSharedProduct($productId, $locale = null)
    {
        $this->requireToken();

        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $locale);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->_showProduct($product);
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

        if (Plugin::getInstance()->getProducts()->deleteProduct($product)) {
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asJson(['success' => true]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product deleted.'));
                $this->redirectToPostedUrl($product);
            }
        } else {
            if (Craft::$app->getRequest()->isAjax()) {
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

        $product = $this->_setProductFromPost();
        ProductHelper::populateProductVariantModels($product, Craft::$app->getRequest()->getParam('variants'));

        $this->enforceProductPermissions($product);


        $existingProduct = (bool)$product->id;

        Db::beginStackedTransaction();

        if (Plugin::getInstance()->getProducts()->saveProduct($product)) {

            Db::commitStackedTransaction();

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product saved.'));

            $this->redirectToPostedUrl($product);
        }

        Db::rollbackStackedTransaction();
        // Since Product may have been ok to save and an ID assigned,
        // but child model validation failed and the transaction rolled back.
        // Since action failed, lets remove the ID that was no persisted.
        if (!$existingProduct) {
            $product->id = null;
        }


        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save product.'));
        Craft::$app->getUrlManager()->setRouteParams([
            'product' => $product
        ]);
    }
}
