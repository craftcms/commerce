<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\base\Exception;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Products Preview Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductsPreviewController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * Previews a product.
     *
     * @throws HttpException
     */
    public function actionPreviewProduct(): Response
    {
        $this->requirePostRequest();

        $product = ProductHelper::populateProductFromPost();

        $this->enforceProductPermissions($product);

        return $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param mixed $productId
     * @param mixed $siteId
     * @return Response
     * @throws Exception
     * @throws HttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionShareProduct($productId, $siteId): Response
    {
        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $siteId);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->enforceProductPermissions($product);

        // Make sure the product actually can be viewed
        if (!Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($product->getType(), $product->siteId)) {
            throw new HttpException(404);
        }

        // Create the token and redirect to the product URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'commerce/products-preview/view-shared-product', ['productId' => $product->id, 'siteId' => $siteId]
        ]);

        $url = UrlHelper::urlWithToken($product->getUrl(), $token);

        return $this->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param mixed $productId
     * @param mixed $site
     * @return Response|null
     * @throws HttpException
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
     */
    protected function enforceProductPermissions(Product $product)
    {
        $this->requirePermission('commerce-manageProductType:' . $product->getType()->uid);
    }

    /**
     * Displays a product.
     *
     * @param Product $product
     * @return Response
     * @throws HttpException
     */
    private function _showProduct(Product $product): Response
    {
        $productType = $product->getType();

        if (!$productType) {
            throw new ServerErrorHttpException('Product type not found.');
        }

        $siteSettings = $productType->getSiteSettings();

        if (!isset($siteSettings[$product->siteId]) || !$siteSettings[$product->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The product ' . $product->id . ' doesn\'t have a URL for the site ' . $product->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($product->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $product->siteId);
        }

        Craft::$app->language = $site->language;

        // Have this product override any freshly queried products with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($product);

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$product->siteId]->template, [
            'product' => $product
        ]);
    }
}
