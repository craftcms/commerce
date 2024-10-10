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
use craft\commerce\Plugin;
use craft\commerce\web\assets\productindex\ProductIndexAsset;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class Products Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductsController extends BaseController
{
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

        // Structure parent
        if (
            $productType->isStructure &&
            (int)$productType->maxLevels !== 1
        ) {
            // Set the initially selected parent
            $product->setParentId($this->request->getParam('parentId'));
        }

        // Set its position in the structure if a before/after param was passed
        if ($productType->isStructure) {
            if ($nextId = $this->request->getParam('before')) {
                $nextEntry = Plugin::getInstance()->getProducts()->getProductById($nextId, $site->id, [
                    'structureId' => $productType->structureId,
                ]);
                Craft::$app->getStructures()->moveBefore($productType->structureId, $product, $nextEntry);
            } elseif ($prevId = $this->request->getParam('after')) {
                $prevEntry = Plugin::getInstance()->getProducts()->getProductById($prevId, $site->id, [
                    'structureId' => $productType->structureId,
                ]);
                Craft::$app->getStructures()->moveAfter($productType->structureId, $product, $prevEntry);
            }
        }

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
}
