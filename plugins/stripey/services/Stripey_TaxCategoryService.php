<?php

namespace Craft;

/**
 * Class Stripey_TaxCategoryService
 * @package Craft
 */
class Stripey_TaxCategoryService extends BaseApplicationComponent
{
    /**
     * @return Stripey_TaxCategoryModel[]
     */
    public function getAll()
    {
        $records = Stripey_TaxCategoryRecord::model()->findAll();
        return Stripey_TaxCategoryModel::populateModels($records);
    }

    /**
     * @param int $id
     * @return Stripey_TaxCategoryModel
     */
    public function getById($id)
    {
        $record = Stripey_TaxCategoryRecord::model()->findById($id);
        return Stripey_TaxCategoryModel::populateModel($record);
    }

    /**
     * @param Stripey_TaxCategoryModel $model
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */    
    public function save(Stripey_TaxCategoryModel $model)
    {
        if ($model->id) {
            $record = Stripey_TaxCategoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax category exists with the ID “{id}”', array('id' => $model->id)));
            }
        } else {
            $record = new Stripey_TaxCategoryRecord();
        }

        $record->name = $model->name;
        $record->code = $model->code;
        $record->description = $model->description;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     * @throws \CDbException
     */
    public function deleteById($id)
    {
        $taxCategory = Stripey_TaxCategoryRecord::model()->findById($id);
        $taxCategory->delete();
    }
}