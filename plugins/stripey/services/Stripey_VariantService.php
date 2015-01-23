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
        $conditions = array('productId' => $id);
        $variant    = Stripey_VariantRecord::model()->findByAttributes($conditions);

        return Stripey_VariantModel::populateModel($variant);
    }

    public function getMasterVariantByProductId($id)
    {
        $conditions = array('productId' => $id);
        $variant    = Stripey_VariantRecord::model()->master()->findByAttributes($conditions);

        return Stripey_VariantModel::populateModel($variant);
    }

    public function saveVariant(Stripey_VariantModel $variant)
    {
        $isNewVariant = !$variant->productId;

        if ($isNewVariant) {
            $variantRecord = new Stripey_VariantRecord();
        } else {
            $variantRecord = Stripey_VariantRecord::model()->findByAttributes(array('productId' => $variant->productId));
        }
        $variantRecord->isMaster  = $variant->isMaster;
        $variantRecord->productId  = $variant->productId;
        $variantRecord->sku       = $variant->sku;
        $variantRecord->price     = $variant->price;
        $variantRecord->width     = $variant->width;
        $variantRecord->height    = $variant->height;
        $variantRecord->length    = $variant->length;
        $variantRecord->weight    = $variant->weight;

        $variantRecord->validate();
        $variant->addErrors($variantRecord->getErrors());

        if (!$variant->hasErrors()) {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try {
                // Save it!
                $variantRecord->save(false);

                // Now that we have a  ID, save it on the model
                if (!$variant->id) {
                    $variant->id = $variantRecord->id;
                }

                if ($transaction !== null) {
                    $transaction->commit();
                }
            } catch (\Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        } else {
            return false;
        }


    }


}