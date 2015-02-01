<?php

namespace Craft;


class Stripey_ProductService extends BaseApplicationComponent
{
    /**
     * @param $id
     * @return Stripey_ProductModel
     */
    public function getById($id)
    {

        $product = Stripey_ProductRecord::model()->findById($id);

        return Stripey_ProductModel::populateModel($product);

    }

    public function deleteProduct($product)
    {
        $product = Stripey_ProductRecord::model()->findById($product->id);
        if ($product->delete()){
            craft()->stripey_variant->disableAllByProductId($product->id);
            return true;
        }else{
            return false;
        }
    }

    public function getOptionTypesForProduct($productId)
    {
        $product = Stripey_ProductRecord::model()->with('optionTypes')->findById($productId);
        return Stripey_OptionTypeModel::populateModels($product->optionTypes);
    }

    /**
     * @param int $productId
     * @return Stripey_VariantModel
     */
    public function getMasterVariant($productId)
    {
        /** @var Stripey_ProductRecord $product */
        $product = Stripey_ProductRecord::model()->findById($productId);
        return Stripey_VariantModel::populateModel($product->master);
    }

    /**
     * Set option types to a product
     *
     * @param int $productId
     * @param int[] $optionTypeIds
     * @return bool
     */
    public function setOptionTypes($productId, $optionTypeIds)
    {
        craft()->db->createCommand()->delete('stripey_product_optiontypes', array('productId' => $productId));

        if ($optionTypeIds) {
            if (!is_array($optionTypeIds)) {
                $optionTypeIds = array($optionTypeIds);
            }

            $values = array();
            foreach ($optionTypeIds as $optionTypeId) {
                $values[] = array($optionTypeId, $productId);
            }

            craft()->db->createCommand()->insertAll('stripey_product_optiontypes', array('optionTypeId', 'productId'), $values);
        }

        return true;
    }
}