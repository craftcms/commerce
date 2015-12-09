<?php
namespace Commerce\Helpers;

use Craft\Commerce_ProductModel as ProductModel;
use Craft\Commerce_VariantModel as VariantModel;

/**
 * Class CommerceVariantMatrixHelper
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Helpers
 * @since     1.0
 */
class CommerceProductHelper
{

    /**
     * Populates a Product model from HUD or POST data
     *
     * @param ProductModel $product
     * @param $data
     */
    public static function populateProductModel(ProductModel &$product, $data)
    {
        if (isset($data['typeId'])) {
            $product->typeId = $data['typeId'];
        }

        if (isset($data['enabled'])) {
            $product->enabled = $data['enabled'];
        }

        $product->postDate = $data['postDate'] ? \Craft\DateTime::createFromString($data['postDate'], \Craft\craft()->timezone) : $product->postDate;
        if (!$product->postDate) {
            $product->postDate = new \Craft\DateTime();
        }
        $product->expiryDate = $data['expiryDate'] ? \Craft\DateTime::createFromString($data['expiryDate'], \Craft\craft()->timezone) : null;

        $product->promotable = $data['promotable'];
        $product->freeShipping = $data['freeShipping'];
        $product->authorId = \Craft\craft()->userSession->id;
        $product->taxCategoryId = $data['taxCategoryId'] ? $data['taxCategoryId'] : $product->taxCategoryId;
        $product->slug = $data['slug'] ? $data['slug'] : $product->slug;
    }

    /**
     * Populates all Variant Models from HUD or POST data
     *
     * @param ProductModel $product
     * @param $data
     */
    public static function populateProductVariantModels(ProductModel &$product, $data)
    {
        $variantData = $data;
        $variants = [];
        $count = 1;

        if(empty($variantData)){
            $variantData = [];
        }

        foreach ($variantData as $key => $variant) {
            if (strncmp($key, 'new', 3) !== 0) {
                $variantModel = \Craft\craft()->commerce_variants->getVariantById($key, $product->locale);
            }else{
                $variantModel = new VariantModel();
            }

            $variantModel->setProduct($product);
            $variantModel->setAttributes($variant);
            $variantModel->sortOrder = $count++;

            if (isset($variant['fields'])) {
                $variantModel->setContentFromPost($variant['fields']);
            }

            if (isset($variant['title'])) {
                $variantModel->getContent()->title = $variant['title'];
            }

            $variants[] = $variantModel;
        }

        $product->setVariants($variants);
    }
}
