<?php

namespace Craft;

/**
 * Class Stripey_StateService
 * @package Craft
 */
class Stripey_StateService extends BaseApplicationComponent
{
    /**
     * @return Stripey_StateModel[]
     */
    public function getAll()
    {
        $records = Stripey_StateRecord::model()->with('country')->findAll(array('order' => 'country.name, t.name'));
        return Stripey_StateModel::populateModels($records);
    }

    /**
     * @param int $id
     * @return Stripey_StateModel
     */
    public function getById($id)
    {
        $record = Stripey_StateRecord::model()->findById($id);
        return Stripey_StateModel::populateModel($record);
    }

    /**
     * @param Stripey_StateModel $model
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */    
    public function save(Stripey_StateModel $model)
    {
        if ($model->id) {
            $record = Stripey_StateRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No state exists with the ID “{id}”', array('id' => $model->id)));
            }
        } else {
            $record = new Stripey_StateRecord();
        }

        $record->name = $model->name;
        $record->abbreviation = $model->abbreviation;
        $record->countryId = $model->countryId;

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
        $State = Stripey_StateRecord::model()->findById($id);
        $State->delete();
    }
}