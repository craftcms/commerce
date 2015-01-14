<?php

namespace Craft;


class Stripey_VariantService extends BaseApplicationComponent
{
    public function getVariantById($id)
    {

        $product = Stripey_VariantRecord::model()->findById($id);

        return Stripey_VariantModel::populateModel($product);

    }

    public function deleteVariant($variant)
    {
        $variant = Stripey_ProductRecord::model()->findById($variant->id);
        return $variant->delete();
    }

    public function getVariantsByProductId($id)
    {
        $conditions = array('productId'=>$id);
        $variant = Stripey_VariantRecord::model()->findByAttributes($conditions);
        return Stripey_VariantModel::populateModel($variant);
    }

    public function getMasterVariantByProductId($id)
    {
        $conditions = array('productId'=>$id);
        $variant = Stripey_VariantRecord::model()->master()->findByAttributes($conditions);
        return Stripey_VariantModel::populateModel($variant);
    }


}