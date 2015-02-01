<?php

namespace Craft;


class Stripey_ProductService extends BaseApplicationComponent
{
    public function getProductById($id)
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

    public function getMasterVariantForProduct($productId)
    {
        $product = Stripey_ProductRecord::model()->findById($productId);
        return Stripey_VariantModel::populateModel($product->master);
    }

}