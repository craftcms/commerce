<?php
namespace Craft;


/**
 * Tax zone service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_TaxZonesService extends BaseApplicationComponent
{
    /*
     * @var
     */
    private $_countriesByTaxZoneId;

    /*
     * @var
     */
    private $_statesByTaxZoneId;

    /**
     * @param bool $withRelations
     *
     * @return Commerce_TaxZoneModel[]
     */
    public function getAllTaxZones($withRelations = true)
    {
        $with = $withRelations ? [
            'countries',
            'states',
            'states.country'
        ] : [];
        $records = Commerce_TaxZoneRecord::model()->with($with)->findAll(['order' => 't.name']);

        return Commerce_TaxZoneModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Commerce_TaxZoneModel|null
     */
    public function getTaxZoneById($id)
    {
        $result = Commerce_TaxZoneRecord::model()->findById($id);

        if ($result){
            return Commerce_TaxZoneModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param Commerce_TaxZoneModel $model
     * @param array $countryIds
     * @param array $stateIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveTaxZone(Commerce_TaxZoneModel $model, $countryIds, $stateIds)
    {
        if ($model->id) {
            $record = Commerce_TaxZoneRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax zone exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_TaxZoneRecord();
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->countryBased = $model->countryBased;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating given ids
        if ($record->countryBased) {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $countryIds);
            $exist = Commerce_CountryRecord::model()->exists($criteria);

            if (!$exist) {
                $model->addError('countries', 'Please select some countries');
            }
        } else {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $stateIds);
            $exist = Commerce_StateRecord::model()->exists($criteria);

            if (!$exist) {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors()) {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try {
                // Save it!
                $record->save(false);

                // Now that we have a record ID, save it on the model
                $model->id = $record->id;

                // Clean out all old links
                Commerce_TaxZoneCountryRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);
                Commerce_TaxZoneStateRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);

                //saving new links
                if ($model->countryBased) {
                    $rows = array_map(function ($id) use ($model) {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'taxZoneId'];
                    $table = Commerce_TaxZoneCountryRecord::model()->getTableName();
                } else {
                    $rows = array_map(function ($id) use ($model) {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'taxZoneId'];
                    $table = Commerce_TaxZoneStateRecord::model()->getTableName();
                }
                craft()->db->createCommand()->insertAll($table, $cols, $rows);

                //If this was the default make all others not the default.
                if ($model->default) {
                    Commerce_TaxZoneRecord::model()->updateAll(['default' => 0],
                        'id != ?', [$record->id]);
                }

                if ($transaction !== null)
                {
                    $transaction->commit();
                }
            } catch (\Exception $e) {
                if ($transaction !== null)
                {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     */
    public function deleteTaxZoneById($id)
    {
        Commerce_TaxZoneRecord::model()->deleteByPk($id);
    }

    /**
     * Returns all countries in a tax zone
     *
     * @param $taxZoneId
     * @return array
     */
    public function getCountriesByTaxZoneId($taxZoneId)
    {
        if(!isset($this->_countriesByTaxZoneId) || !array_key_exists($taxZoneId, $this->_countriesByTaxZoneId)){

            $results = Commerce_TaxZoneCountryRecord::model()->with('country')->findAllByAttributes([
                'taxZoneId' => $taxZoneId
            ]);

            $countries = [];

            foreach($results as $result)
            {
                $countries[] = Commerce_CountryModel::populateModel($result->country);
            }

            $this->_countriesByTaxZoneId[$taxZoneId] = $countries;
        }

        return $this->_countriesByTaxZoneId[$taxZoneId];
    }

    /**
     * Returns all states in a tax zone
     *
     * @param $taxZoneId
     * @return array
     */
    public function getStatesByTaxZoneId($taxZoneId)
    {
        if(!isset($this->_statesByTaxZoneId) || !array_key_exists($taxZoneId, $this->_statesByTaxZoneId)) {

            $results = Commerce_TaxZoneStateRecord::model()->with('state')->findAllByAttributes([
            'taxZoneId' => $taxZoneId
            ]);

            $states = [];
            foreach($results as $result)
            {
                $states[] =  Commerce_StateModel::populateModel($result->state);
            }

            $this->_statesByTaxZoneId[$taxZoneId] = $states;
        }

        return $this->_statesByTaxZoneId[$taxZoneId];
    }

}
