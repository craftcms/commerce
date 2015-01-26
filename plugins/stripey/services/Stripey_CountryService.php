<?php

namespace Craft;

/**
 * Class Stripey_CountryService
 * @package Craft
 */
class Stripey_CountryService extends BaseApplicationComponent
{
    /**
     * @return Stripey_CountryModel[]
     */
    public function getAll()
    {
        $records = Stripey_CountryRecord::model()->findAll(array('order' => 'name'));
        return Stripey_CountryModel::populateModels($records);
    }

    /**
     * @param int $id
     * @return Stripey_CountryModel
     */
    public function getById($id)
    {
        $record = Stripey_CountryRecord::model()->findById($id);
        return Stripey_CountryModel::populateModel($record);
    }

    /**
     * @param Stripey_CountryModel $model
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */    
    public function save(Stripey_CountryModel $model)
    {
        if ($model->id) {
            $record = Stripey_CountryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No country exists with the ID “{id}”', array('id' => $model->id)));
            }
        } else {
            $record = new Stripey_CountryRecord();
        }

        $record->name = $model->name;
        $record->iso = $model->iso;
        $record->stateRequired = $model->stateRequired;

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
        $Country = Stripey_CountryRecord::model()->findById($id);
        $Country->delete();
    }
}