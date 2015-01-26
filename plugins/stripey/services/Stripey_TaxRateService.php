<?php

namespace Craft;

/**
 * Class Stripey_TaxRateService
 * @package Craft
 */
class Stripey_TaxRateService extends BaseApplicationComponent
{
    /**
     * @return Stripey_TaxRateModel[]
     */
    public function getAll()
    {
        $records = Stripey_TaxRateRecord::model()->with(array('taxZone', 'taxCategory'))->findAll(array('order' => 't.name'));
        return Stripey_TaxRateModel::populateModels($records);
    }

    /**
     * @param int $id
     * @return Stripey_TaxRateModel
     */
    public function getById($id)
    {
        $record = Stripey_TaxRateRecord::model()->findById($id);
        return Stripey_TaxRateModel::populateModel($record);
    }

    /**
     * @param Stripey_TaxRateModel $model
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */    
    public function save(Stripey_TaxRateModel $model)
    {
        if ($model->id) {
            $record = Stripey_TaxRateRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax rate exists with the ID “{id}”', array('id' => $model->id)));
            }
        } else {
            $record = new Stripey_TaxRateRecord();
        }

        $record->name = $model->name;
        $record->rate = $model->rate;
        $record->include = $model->include;
        $record->showInLabel = $model->showInLabel;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId = $model->taxZoneId;

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
        $TaxRate = Stripey_TaxRateRecord::model()->findById($id);
        $TaxRate->delete();
    }
}