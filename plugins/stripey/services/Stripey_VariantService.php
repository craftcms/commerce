<?php

namespace Craft;


class Stripey_VariantService extends BaseApplicationComponent
{
    /**
     * @param int $id
     * @return Stripey_VariantModel
     */
    public function getById($id)
    {
        $product = Stripey_VariantRecord::model()->findById($id);
        return Stripey_VariantModel::populateModel($product);
    }

    /**
     * @param int $id
     */
    public function deleteById($id)
    {
        $this->unsetOptionValues($id);
        Stripey_VariantRecord::model()->deleteByPk($id);
    }

    /**
     * @param $variant
     */
    public function disableVariant($variant)
    {
        $variant = Stripey_ProductRecord::model()->findById($variant->id);
        $variant->deletedAt = DateTimeHelper::currentTimeForDb();
        $variant->saveAttributes(array('deletedAt'));
    }

    /**
     * @param int $productId
     */
    public function disableAllByProductId($productId)
    {
        $variants = $this->getAllByProductId($productId);
        foreach ($variants as $variant){
            $this->disableVariant($variant);
        }
    }

    /**
     * @param int $id
     * @param bool $isMaster null / true / false. All by default
     * @return Stripey_VariantModel[]
     */
    public function getAllByProductId($id, $isMaster = null)
    {
        $conditions = array('productId' => $id);
        if(!is_null($isMaster)) {
            $conditions['isMaster'] = $isMaster;
        }

        $variants = Stripey_VariantRecord::model()->findAllByAttributes($conditions);
        return Stripey_VariantModel::populateModels($variants);
    }

    /**
     * Save a model into DB
     *
     * @param Stripey_VariantModel $model
     * @return bool
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(Stripey_VariantModel $model)
    {
        if($model->id) {
            $record = Stripey_VariantRecord::model()->findById($model->id);

            if(!$record) {
                throw new HttpException(404);
            }
        } else {
            $record = new Stripey_VariantRecord();
        }

        $record->isMaster  = $model->isMaster;
        $record->productId = $model->productId;
        $record->sku       = $model->sku;
        $record->price     = $model->price;
        $record->width     = $model->width;
        $record->height    = $model->height;
        $record->length    = $model->length;
        $record->weight    = $model->weight;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * Set option values to a variant
     *
     * @param int $variantId
     * @param int[] $optionValueIds
     * @return bool
     */
    public function setOptionValues($variantId, $optionValueIds)
    {
        $this->unsetOptionValues($variantId);

        if ($optionValueIds) {
            if (!is_array($optionValueIds)) {
                $optionValueIds = array($optionValueIds);
            }

            $values = array();
            foreach ($optionValueIds as $optionValueId) {
                $values[] = array($optionValueId, $variantId);
            }

            craft()->db->createCommand()->insertAll('stripey_variant_optionvalues', array('optionValueId', 'variantId'), $values);
        }

        return true;
    }

    /**
     * Delete all variant-optionValue relations by variant id
     *
     * @param int $variantId
     */
    public function unsetOptionValues($variantId)
    {
        Stripey_VariantOptionValueRecord::model()->deleteAllByAttributes(array('variantId' => $variantId));
    }
}