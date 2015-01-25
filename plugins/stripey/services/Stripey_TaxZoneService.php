<?php

namespace Craft;

/**
 * Class Stripey_TaxZoneService
 * @package Craft
 */
class Stripey_TaxZoneService extends BaseApplicationComponent
{
    /**
     * @return Stripey_TaxZoneModel[]
     */
    public function getAll()
    {
        $records = Stripey_TaxZoneRecord::model()->with(array('countries', 'states', 'states.country'))->findAll(array('order' => 't.name'));
        return Stripey_TaxZoneModel::populateModels($records);
    }

    /**
     * @param int $id
     * @return Stripey_TaxZoneModel
     */
    public function getById($id)
    {
        $record = Stripey_TaxZoneRecord::model()->findById($id);
        return Stripey_TaxZoneModel::populateModel($record);
    }

    /**
     * @param Stripey_TaxZoneModel $model
     * @param array $countriesIds
     * @param array $statesIds
     * @return bool
     * @throws Exception
     */
    public function save(Stripey_TaxZoneModel $model, $countriesIds, $statesIds)
    {
        if ($model->id) {
            $record = Stripey_TaxZoneRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax zone exists with the ID “{id}”', array('id' => $model->id)));
            }
        } else {
            $record = new Stripey_TaxZoneRecord();
        }

        //remembering which links should be clean
        $deleteOldCountries = $deleteOldStates = false;
        if($record->id) {
            if($record->countryBased) {
                $deleteOldCountries = true;
            } else {
                $deleteOldStates = true;
            }
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->countryBased = $model->countryBased;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating given ids
        if($record->countryBased) {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $countriesIds);
            $exist = Stripey_CountryRecord::model()->exists($criteria);

            if(!$exist) {
                $model->addError('countries', 'Please select some countries');
            }
        } else {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $statesIds);
            $exist = Stripey_StateRecord::model()->exists($criteria);

            if(!$exist) {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            //deleting old links
            if($deleteOldCountries) {
                Stripey_TaxZoneCountryRecord::model()->deleteAll('taxZoneId = ?', array($record->id));
            }

            if($deleteOldStates) {
                Stripey_TaxZoneStateRecord::model()->deleteAll('taxZoneId = ?', array($record->id));
            }

            //saving new links
            if($model->countryBased) {
                $rows = array_map(function($id) use($model) {
                    return array($id, $model->id);
                }, $countriesIds);
                $cols = array('countryId', 'taxZoneId');
                $table = Stripey_TaxZoneCountryRecord::model()->getTableName();
            } else {
                $rows = array_map(function($id) use($model) {
                    return array($id, $model->id);
                }, $statesIds);
                $cols = array('stateId', 'taxZoneId');
                $table = Stripey_TaxZoneStateRecord::model()->getTableName();
            }
            craft()->db->createCommand()->insertAll($table, $cols, $rows);

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
        $TaxZone = Stripey_TaxZoneRecord::model()->findById($id);
        $TaxZone->delete();
    }
}