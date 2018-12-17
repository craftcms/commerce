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
use yii\web\HttpException;

/**
 * Class CommerceVariantMatrixHelper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product
{
    // Public Methods
    // =========================================================================

    /**
     * Populates all Variant Models from HUD or POST data
     *
     * @param ProductModel $product
     * @param               $variant
     * @param               $key
     * @return Variant
     */
    public static function populateProductVariantModel(ProductModel $product, $variant, $key): Variant
    {
        $productId = $product->id;

        $newVariant = 0 === strpos($key, 'new');
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
        $variantModel->price = LocalizationHelper::normalizeNumber($variant['price']);
        $variantModel->width = isset($variant['width']) ? LocalizationHelper::normalizeNumber($variant['width']) : null;
        $variantModel->height = isset($variant['height']) ? LocalizationHelper::normalizeNumber($variant['height']) : null;
        $variantModel->length = isset($variant['length']) ? LocalizationHelper::normalizeNumber($variant['length']) : null;
        $variantModel->weight = isset($variant['weight']) ? LocalizationHelper::normalizeNumber($variant['weight']) : null;
        $variantModel->stock = isset($variant['stock']) ? LocalizationHelper::normalizeNumber($variant['stock']) : null;
        $variantModel->hasUnlimitedStock = (bool)($variant['hasUnlimitedStock'] ?? 0);
        $variantModel->minQty = LocalizationHelper::normalizeNumber($variant['minQty']);
        $variantModel->maxQty = LocalizationHelper::normalizeNumber($variant['maxQty']);

        if (isset($variant['fields'])) {
            $variantModel->setFieldValues($variant['fields']);
        }

        if (!empty($variant['title'])) {
            $variantModel->title = $variant['title'];
        }

        return $variantModel;
    }

    /**
     * @return ProductModel
     * @throws HttpException
     */
    public static function populateProductFromPost(): ProductModel
    {
        $request = Craft::$app->getRequest();
        $productId = $request->getBodyParam('productId');
        $siteId = $request->getBodyParam('siteId');

        if ($productId) {
            $product = Plugin::getInstance()->getProducts()->getProductById($productId, $siteId);

            if (!$product) {
                throw new HttpException(404, Craft::t('commerce', 'No product with the ID “{id}”', ['id' => $productId]));
            }
        } else {
            $product = new ProductModel();
        }

        $product->typeId = $request->getBodyParam('typeId');
        $product->siteId = $siteId ?? $product->siteId;
        $product->enabled = (bool)$request->getBodyParam('enabled');
        if (($postDate = Craft::$app->getRequest()->getBodyParam('postDate')) !== null) {
            $product->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        if (($expiryDate = Craft::$app->getRequest()->getBodyParam('expiryDate')) !== null) {
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

        if ($request->getBodyParam('variants')) {
            $product->setVariants($request->getBodyParam('variants'));
        }

        return $product;
    }
}
