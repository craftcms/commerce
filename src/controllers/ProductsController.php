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
use craft\commerce\web\assets\productindex\ProductIndexAsset;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\MissingComponentException;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\base\Model;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
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
    protected array $ignorePluginPermission = ['save-product', 'duplicate-product', 'delete-product'];

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
     * @throws InvalidConfigException
     */
    public function actionProductIndex(?string $productTypeHandle = null): Response
    {
        $this->getView()->registerAssetBundle(ProductIndexAsset::class);
        return $this->renderTemplate('commerce/products/_index', [
            'productTypeHandle' => $productTypeHandle,
        ]);
    }

    public function actionVariantIndex(): Response
    {
        return $this->renderTemplate('commerce/variants/_index');
    }

    public function actionCreate(?string $productType = null)
    {
        if ($productType) {
            $productTypeHandle = $productType;
        } else {
            $productTypeHandle = $this->request->getRequiredBodyParam('productType');
        }

        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($productTypeHandle);
        if (!$productType) {
            throw new BadRequestHttpException("Invalid product type handle: $productTypeHandle");
        }

        $sitesService = Craft::$app->getSites();
        $siteId = $this->request->getBodyParam('siteId');

        if ($siteId) {
            $site = $sitesService->getSiteById($siteId);
            if (!$site) {
                throw new BadRequestHttpException("Invalid site ID: $siteId");
            }
        } else {
            $site = Cp::requestedSite();
            if (!$site) {
                throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
            }
        }

        $editableSiteIds = $sitesService->getEditableSiteIds();
        if (!in_array($site->id, $editableSiteIds)) {
            // Go with the first one
            $site = $sitesService->getSiteById($editableSiteIds[0]);
        }

        $user = static::currentUser();

        // Create & populate the draft
        $product = Craft::createObject(Product::class);
        $product->siteId = $site->id;
        $product->typeId = $productType->id;
        $product->enabled = true;

        // Make sure the user is allowed to create this entry
        if (!Craft::$app->getElements()->canSave($product, $user)) {
            throw new ForbiddenHttpException('User not authorized to create this product.');
        }

        // Title & slug
        $product->title = $this->request->getParam('title');
        $product->slug = $this->request->getParam('slug');
        if ($product->title && !$product->slug) {
            $product->slug = ElementHelper::generateSlug($product->title, null, $site->language);
        }
        if (!$product->slug) {
            $product->slug = ElementHelper::tempSlug();
        }

        // Pause time so postDate will definitely be equal to dateCreated, if not explicitly defined
        DateTimeHelper::pause();

        // Post & expiry dates
        if (($postDate = $this->request->getParam('postDate')) !== null) {
            $product->postDate = DateTimeHelper::toDateTime($postDate);
        } else {
            $product->postDate = DateTimeHelper::now();
        }

        if (($expiryDate = $this->request->getParam('expiryDate')) !== null) {
            $product->expiryDate = DateTimeHelper::toDateTime($expiryDate);
        }

        // Custom fields
        foreach ($product->getFieldLayout()->getCustomFields() as $field) {
            if (($value = $this->request->getParam($field->handle)) !== null) {
                $product->setFieldValue($field->handle, $value);
            }
        }

        // Save it
        $product->setScenario(Element::SCENARIO_ESSENTIALS);
        $success = Craft::$app->getDrafts()->saveElementAsDraft($product, $user->id, markAsSaved: false);

        // Resume time
        DateTimeHelper::resume();

        if (!$success) {
            return $this->asModelFailure($product, Craft::t('app', 'Couldn’t create {type}.', [
                'type' => Product::lowerDisplayName(),
            ]), 'product');
        }

        $editUrl = $product->getCpEditUrl();

        $response = $this->asModelSuccess($product, Craft::t('app', '{type} created.', [
            'type' => Product::displayName(),
        ]), 'product', array_filter([
            'cpEditUrl' => $this->request->getIsCpRequest() ? $editUrl : null,
        ]));

        if (!$this->request->getAcceptsJson()) {
            $response->redirect(UrlHelper::urlWithParams($editUrl, [
                'fresh' => 1,
            ]));
        }

        return $response;
    }

    /**
     * Deletes a product.
     *
     * @throws Exception if you try to edit a non-existent ID.
     * @throws Throwable
     */
    public function actionDeleteProduct(): Response
    {
        $this->requirePostRequest();

        $productId = $this->request->getRequiredParam('productId');
        $product = Plugin::getInstance()->getProducts()->getProductById($productId);

        $this->enforceDeleteProductPermissions($product);

        if (!$product) {
            throw new Exception(Craft::t('commerce', 'No product exists with the ID “{id}”.',
                ['id' => $productId]));
        }

        $this->enforceDeleteProductPermissions($product);

        if (!Craft::$app->getElements()->deleteElement($product)) {
            return $this->asModelFailure(
                $product,
                Craft::t('commerce', 'Couldn’t delete product.'),
                'product'
            );
        }

        return $this->asModelSuccess(
            $product,
            Craft::t('commerce', 'Product deleted.'),
            'product'
        );
    }

    /**
     * Save a new or existing product.
     *
     * @param bool $duplicate Whether the product should be duplicated
     * @throws Exception
     * @throws HttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveProduct(bool $duplicate = false): ?Response
    {
        $this->requirePostRequest();

        // Get the requested product
        $oldProduct = ProductHelper::productFromPost($this->request);

        $user = Craft::$app->getUser()->getIdentity();

        $this->enforceEditProductPermissions($oldProduct);

        if ($this->request->getBodyParam('typeId') !== null && !Plugin::getInstance()->getProductTypes()->hasPermission($user, $oldProduct->getType(), 'commerce-createProducts')) {
            if ($oldProduct->id === null || $duplicate === true) {
                throw new ForbiddenHttpException('User not permitted to create a product for this type.');
            }
        }

        $elementsService = Craft::$app->getElements();

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // If we're duplicating the product, swap $product with the duplicate
            if ($duplicate) {
                try {
                    $product = $elementsService->duplicateElement($oldProduct);
                } catch (InvalidElementException $e) {
                    $transaction->rollBack();

                    /** @var Product $clone */
                    $clone = $e->element;

                    $message = Craft::t('commerce', 'Couldn’t duplicate product.');
                    if ($this->request->getAcceptsJson()) {
                        return $this->asModelFailure(
                            $clone,
                            $message,
                            'product'
                        );
                    }

                    $oldProduct->addErrors($clone->getErrors());

                    return $this->asModelFailure(
                        $oldProduct,
                        $message,
                        'product'
                    );
                } catch (Throwable) {
                    throw new ServerErrorHttpException(Craft::t('commerce', 'An error occurred when duplicating the product.'), 0);
                }
            } else {
                $product = $oldProduct;
            }

            // Now populate the rest of it from the post data
            ProductHelper::populateProductFromPost($product, $this->request);

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
                $message = Craft::t('commerce', 'Couldn’t save product.');
                if ($this->request->getAcceptsJson()) {
                    return $this->asModelFailure(
                        $product,
                        $message,
                        'product'
                    );
                }

                if ($duplicate) {
                    // Add validation errors on the original product
                    $oldProduct->addErrors($product->getErrors());
                }
                return $this->asModelFailure(
                    $oldProduct,
                    $message,
                    'product'
                );
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->asModelSuccess(
            $product,
            Craft::t('commerce', 'Product saved.'),
            'product',
            [
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->getStatus(),
                'url' => $product->getUrl(),
                'cpEditUrl' => $product->getCpEditUrl(),
            ]
        );
    }

    /**
     * Duplicates a product.
     *
     * @throws InvalidRouteException
     * @since 3.1.3
     */
    public function actionDuplicateProduct(): ?Response
    {
        return $this->runAction('save-product', ['duplicate' => true]);
    }

    /**
     * @param Product $product
     * @throws ForbiddenHttpException
     * @since 3.4.8
     */
    protected function enforceEditProductPermissions(Product $product): void
    {
        if (!Craft::$app->getElements()->canView($product)) {
            throw new ForbiddenHttpException('User is not permitted to edit this product');
        }
    }

    /**
     * @param Product $product
     * @throws ForbiddenHttpException
     * @since 3.4.8
     */
    protected function enforceDeleteProductPermissions(Product $product): void
    {
        if (!Craft::$app->getElements()->canDelete($product)) {
            throw new ForbiddenHttpException('User is not permitted to delete this product');
        }
    }
}
