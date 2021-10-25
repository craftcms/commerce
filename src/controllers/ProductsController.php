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
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\commerce\web\assets\editproduct\EditProductAsset;
use craft\commerce\web\assets\productindex\ProductIndexAsset;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\MissingComponentException;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\Site;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Products Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductsController extends BaseController
{
    /**
     * @var string[] The action names that bypass the "Access Craft Commerce" permission.
     */
    protected $ignorePluginPermission = ['save-product', 'duplicate-product', 'delete-product'];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->requirePermission('commerce-manageProducts');
    }

    /**
     * @inheritDoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!in_array($action->id, $this->ignorePluginPermission)) {
            $this->requirePermission('accessPlugin-commerce');
        }

        return true;
    }

    /**
     * @return Response
     * @throws InvalidConfigException
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
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public function actionEditProduct(string $productTypeHandle, int $productId = null, string $siteHandle = null, Product $product = null): Response
    {
        $variables = compact('productTypeHandle', 'productId', 'product');

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new NotFoundHttpException('Invalid site handle: ' . $siteHandle);
            }
        }

        $this->_prepEditProductVariables($variables);

        /** @var Product $product */
        $product = $variables['product'];

        if ($product->id === null) {
            $variables['title'] = Craft::t('commerce', 'Create a new product');
        } else {
            $variables['title'] = $product->title;
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce/products/' . $variables['productTypeHandle'] . '/{id}-{slug}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] .
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_prepVariables($variables);

        if (!$product->getType()->hasVariants) {
            $this->getView()->registerJs('Craft.Commerce.initUnlimitedStockCheckbox($("#details"));');
        }

        // Enable Live Preview?
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'], $variables['site']->id)) {
            $this->getView()->registerJs('Craft.LivePreview.init(' . Json::encode([
                    'fields' => '#fields > .flex-fields > .field',
                    'extraFields' => '#details',
                    'previewUrl' => $product->getUrl(),
                    'previewAction' => Craft::$app->getSecurity()->hashData('commerce/products-preview/preview-product'),
                    'previewParams' => [
                        'typeId' => $variables['productType']->id,
                        'productId' => $product->id,
                        'siteId' => $product->siteId,
                    ],
                ]) . ');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($product->id !== null) {
                // If the product is enabled, use its main URL as its share URL.
                if ($product->getStatus() == Product::STATUS_LIVE) {
                    $variables['shareUrl'] = $product->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('commerce/products-preview/share-product', [
                        'productId' => $product->id,
                        'siteId' => $product->siteId,
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
     * @throws Throwable
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

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t delete product.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product,
            ]);
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Product deleted.'));
        return $this->redirectToPostedUrl($product);
    }

    /**
     * Save a new or existing product.
     *
     * @param bool $duplicate Whether the product should be duplicated
     * @return Response|null
     * @throws Exception
     * @throws HttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveProduct(bool $duplicate = false)
    {
        $this->requirePostRequest();

        // Get the requested product
        $request = Craft::$app->getRequest();
        $oldProduct = ProductHelper::productFromPost($request);
        $variants = $request->getBodyParam('variants');
        $this->enforceProductPermissions($oldProduct);
        $elementsService = Craft::$app->getElements();

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // If we're duplicating the product, swap $product with the duplicate
            if ($duplicate) {
                try {
                    $originalVariantIds = ArrayHelper::getColumn($oldProduct->getVariants(), 'id');
                    $product = $elementsService->duplicateElement($oldProduct);
                    $duplicatedVariantIds = ArrayHelper::getColumn($product->getVariants(), 'id');

                    $newVariants = [];
                    foreach ($variants as $key => $postedVariant) {
                        if (strpos($key, 'new') === 0) {
                            $newVariants[$key] = $postedVariant;
                        } else {
                            $index = array_search($key, $originalVariantIds);
                            if ($index !== false) {
                                $newVariants[$duplicatedVariantIds[$index]] = $postedVariant;
                            }
                        }
                    }
                    $variants = $newVariants;
                } catch (InvalidElementException $e) {
                    $transaction->rollBack();

                    /** @var Product $clone */
                    $clone = $e->element;

                    if ($request->getAcceptsJson()) {
                        return $this->asJson([
                            'success' => false,
                            'errors' => $clone->getErrors(),
                        ]);
                    }

                    $this->setFailFlash(Craft::t('commerce', 'Couldn’t duplicate product.'));

                    // Send the original product back to the template, with any validation errors on the clone
                    $oldProduct->addErrors($clone->getErrors());
                    Craft::$app->getUrlManager()->setRouteParams([
                        'product' => $oldProduct,
                    ]);

                    return null;
                } catch (\Throwable $e) {
                    throw new ServerErrorHttpException(Craft::t('commerce', 'An error occurred when duplicating the product.'), 0, $e);
                }
            } else {
                $product = $oldProduct;
            }

            // Now populate the rest of it from the post data
            ProductHelper::populateProductFromPost($product, $request);

            $product->setVariants($variants);

            // Save the product (finally!)
            if ($product->enabled && $product->enabledForSite) {
                $product->setScenario(Element::SCENARIO_LIVE);
            }

            $success = $elementsService->saveElement($product);
            if (!$success && $duplicate && $product->getScenario() === Element::SCENARIO_LIVE) {
                // Try again with the product disabled
                $product->enabled = false;
                $product->setScenario(Model::SCENARIO_DEFAULT);
                $success = $elementsService->saveElement($product);
            }

            if (!$success) {
                $transaction->rollBack();

                if ($request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => false,
                        'errors' => $product->getErrors(),
                    ]);
                }

                $this->setFailFlash(Craft::t('commerce', 'Couldn’t save product.'));

                if ($duplicate) {
                    // Add validation errors on the original product
                    $oldProduct->addErrors($product->getErrors());
                }
                Craft::$app->getUrlManager()->setRouteParams([
                    'product' => $oldProduct,
                ]);

                return null;
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->getStatus(),
                'url' => $product->getUrl(),
                'cpEditUrl' => $product->getCpEditUrl(),
            ]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Product saved.'));

        return $this->redirectToPostedUrl($product);
    }

    /**
     * Duplicates a product.
     *
     * @return Response|null
     * @since 3.1.3
     */
    public function actionDuplicateProduct()
    {
        return $this->runAction('save-product', ['duplicate' => true]);
    }

    /**
     * @param Product $product
     * @throws HttpException
     * @throws InvalidConfigException
     */
    protected function enforceProductPermissions(Product $product)
    {
        $this->requirePermission('commerce-manageProductType:' . $product->getType()->uid);
    }


    /**
     * @param array $variables
     * @throws InvalidConfigException
     */
    private function _prepVariables(array &$variables)
    {
        $variables['tabs'] = [];

        /** @var ProductType $productType */
        $productType = $variables['productType'];
        /** @var Product $product */
        $product = $variables['product'];

        $form = $productType->getProductFieldLayout()->createForm($product);
        $variables['tabs'] = $form->getTabMenu();
        $variables['fieldsHtml'] = $form->render();
    }

    /**
     * @param array $variables
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
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
            throw new HttpException(400, Craft::t('commerce', 'Wrong product type specified'));
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
