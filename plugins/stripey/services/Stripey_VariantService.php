<?php

namespace Craft;


class Stripey_VariantService extends BaseApplicationComponent
{
    public function getById($id)
    {

        $product = Stripey_VariantRecord::model()->findById($id);

        return Stripey_VariantModel::populateModel($product);

    }

    public function deleteVariant($variant)
    {
        $variant = Stripey_ProductRecord::model()->findById($variant->id);
        $variant->deletedAt = DateTimeHelper::currentTimeForDb();
        return $variant->save();
    }

    public function deleteAllVariants($variants)
    {
        foreach ($variants as$variant){
            $this->deleteVariant($variant);
        }
        return true;
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

    public function getMasterVariantByProductId($id)
    {
        $conditions = array('productId' => $id, 'isMaster' => true);
        $variant    = Stripey_VariantRecord::model()->master()->findByAttributes($conditions);

        return Stripey_VariantModel::populateModel($variant);
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
}