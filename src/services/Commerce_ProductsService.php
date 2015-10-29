<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Product service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ProductsService extends BaseApplicationComponent
{
    /**
     * @param int $id
     * @param int $localeId
     *
     * @return Commerce_ProductModel
     */
    public function getById($id, $localeId = null)
    {
        return craft()->elements->getElementById($id, 'Commerce_Product', $localeId);
    }


    /**
     * @param Commerce_ProductModel $product
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function save(Commerce_ProductModel $product)
    {
        if (!$product->id) {
            $record = new Commerce_ProductRecord();
        } else {
            $record = Commerce_ProductRecord::model()->findById($product->id);

            if (!$record) {
                throw new Exception(Craft::t('No product exists with the ID “{id}”',
                    ['id' => $product->id]));
            }
        }

        $record->postDate = $product->postDate;
        $record->expiryDate = $product->expiryDate;
        $record->typeId = $product->typeId;
        $record->authorId = $product->authorId;
        $record->promotable = $product->promotable;
        $record->freeShipping = $product->freeShipping;
        $record->taxCategoryId = $product->taxCategoryId;

        $record->validate();
        $product->addErrors($record->getErrors());

        $variantsValid = true;
        foreach ($product->getVariants() as $variant) {
            if (!craft()->commerce_variants->validateVariant($variant)) {
                $variantsValid = false;
            }
        }

        CommerceDbHelper::beginStackedTransaction();
        try {
            if (!$product->hasErrors() && $variantsValid) {
                if (craft()->elements->saveElement($product)) {
                    $record->id = $product->id;
                    $record->save(false);

                    $keepVariantIds = [];
                    $oldVariantIds = craft()->db->createCommand()
                        ->select('id')
                        ->from('commerce_variants')
                        ->where('productId = :productId', [':productId' => $product->id])
                        ->queryColumn();

                    foreach ($product->getVariants() as $variant) {
                        $variant->productId = $product->id;
                        craft()->commerce_variants->save($variant);
                        $keepVariantIds[] = $variant->id;
                    }

                    foreach (array_diff($oldVariantIds, $keepVariantIds) as $keepId) {
                        craft()->commerce_variants->deleteById($keepId);
                    }

                    CommerceDbHelper::commitStackedTransaction();

                    return true;
                }
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        CommerceDbHelper::rollbackStackedTransaction();

        return false;
    }


    /**
     * @param Commerce_ProductModel $product
     *
     * @return bool
     * @throws \CDbException
     */
    public function delete($product)
    {
        $product = Commerce_ProductRecord::model()->findById($product->id);
        if ($product) {
            $variants = craft()->commerce_variants->getAllByProductId($product->id);
            if (craft()->elements->deleteElementById($product->id)) {
                foreach ($variants as $v) {
                    craft()->elements->deleteElementById($v->id);
                }

                return true;
            } else {
                return false;
            }
        }
    }

}
