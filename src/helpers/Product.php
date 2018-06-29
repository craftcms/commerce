<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\commerce\elements\Product as ProductModel;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\helpers\Localization as LocalizationHelper;

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
            $variantModel->setFieldValuesFromRequest("variants.{$key}.fields");
        }

        if (!empty($variant['title'])) {
            $variantModel->title = $variant['title'];
        }

        return $variantModel;
    }
}
