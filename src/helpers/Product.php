<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\elements\Product as ProductModel;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\helpers\DateTimeHelper;
use craft\helpers\Localization as LocalizationHelper;
use craft\web\Request;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;

/**
 * Product helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product
{
    /**
     * Populates all Variant Models from HUD or POST data
     *
     * @param ProductModel $product
     * @param               $variant
     * @param               $key
     * @return Variant
     * @throws InvalidConfigException
     */
    public static function populateProductVariantModel(ProductModel $product, $variant, $key): Variant
    {
        $productId = $product->id;

        $newVariant = str_starts_with($key, 'new');
        if ($productId && !$newVariant) {
            $variantModel = Plugin::getInstance()->getVariants()->getVariantById($key, $product->siteId);
        } else {
            $variantModel = new Variant();
        }

        // Need to set the product now so that the variant custom fields
        $variantModel->setProduct($product);

        $variantModel->enabled = (bool)($variant['enabled'] ?? 1);
        $variantModel->isDefault = (bool)($variant['isDefault'] ?? 0);
        $variantModel->sku = $variant['sku'] ?? '';
        $variantModel->price = (float)LocalizationHelper::normalizeNumber($variant['price']);
        $variantModel->width = isset($variant['width']) ? (float)LocalizationHelper::normalizeNumber($variant['width']) : null;
        $variantModel->height = isset($variant['height']) ? (float)LocalizationHelper::normalizeNumber($variant['height']) : null;
        $variantModel->length = isset($variant['length']) ? (float)LocalizationHelper::normalizeNumber($variant['length']) : null;
        $variantModel->weight = isset($variant['weight']) ? (float)LocalizationHelper::normalizeNumber($variant['weight']) : null;
        $variantModel->stock = isset($variant['stock']) ? (int)LocalizationHelper::normalizeNumber($variant['stock']) : null;
        $variantModel->hasUnlimitedStock = (bool)($variant['hasUnlimitedStock'] ?? 0);
        $variantModel->minQty = $variant['minQty'] === null || $variant['minQty'] === '' ? null : (int)LocalizationHelper::normalizeNumber($variant['minQty']);
        $variantModel->maxQty = $variant['maxQty'] === null || $variant['maxQty'] === '' ? null : (int)LocalizationHelper::normalizeNumber($variant['maxQty']);

        if (isset($variant['fields'])) {
            $variantModel->setFieldValues($variant['fields']);
        }

        if (isset($variant['title'])) {
            $variantModel->title = $variant['title'];
        }

        return $variantModel;
    }

    /**
     * Instantiates the product specified by the post data.
     *
     * @param Request|null $request
     * @throws NotFoundHttpException
     * @since 3.1.3
     */
    public static function productFromPost(Request $request = null): ProductModel
    {
        if ($request === null) {
            $request = Craft::$app->getRequest();
        }

        $productId = $request->getBodyParam('productId');
        $siteId = $request->getBodyParam('siteId');

        if ($productId) {
            $product = Plugin::getInstance()->getProducts()->getProductById($productId, $siteId);

            if (!$product) {
                throw new NotFoundHttpException(Craft::t('commerce', 'No product with the ID “{id}”', ['id' => $productId]));
            }
        } else {
            $product = new ProductModel();
            $product->typeId = $request->getBodyParam('typeId');
            $product->siteId = $siteId ?? $product->siteId;
        }

        return $product;
    }

    /**
     * Populates a product from the post data.
     *
     * @param ProductModel|null $product
     * @param Request|null $request
     * @throws NotFoundHttpException
     */
    public static function populateProductFromPost(ProductModel $product = null, Request $request = null): ProductModel
    {
        if ($request === null) {
            $request = Craft::$app->getRequest();
        }

        if ($product === null) {
            $product = static::productFromPost($request);
        }

        $product->enabled = (bool)$request->getBodyParam('enabled');
        if (($postDate = $request->getBodyParam('postDate')) !== null) {
            $product->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        if (($expiryDate = $request->getBodyParam('expiryDate')) !== null) {
            $product->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        $product->promotable = (bool)$request->getBodyParam('promotable');
        $product->availableForPurchase = (bool)$request->getBodyParam('availableForPurchase');
        $product->freeShipping = (bool)$request->getBodyParam('freeShipping');
        $product->taxCategoryId = $request->getBodyParam('taxCategoryId');
        $product->shippingCategoryId = $request->getBodyParam('shippingCategoryId');
        $product->slug = $request->getBodyParam('slug');

        $product->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $product->enabledForSite);
        $product->title = $request->getBodyParam('title', $product->title);

        $product->setFieldValuesFromRequest('fields');
        $product->updateTitle();

        if ($variants = $request->getBodyParam('variants')) {
            $product->setVariants($variants);
        } else {
            $product->setVariants([]);
        }

        return $product;
    }
}
