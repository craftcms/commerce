<?php
namespace craft\commerce\services;

use craft\commerce\helpers\Db;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\models\TaxZone;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\State as StateRecord;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\commerce\records\TaxZoneCountry as TaxZoneCountryRecord;
use craft\commerce\records\TaxZoneState as TaxZoneStateRecord;
use yii\base\Component;

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
class TaxZones extends Component
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
     * @return TaxZone[]
     */
    public function getAllTaxZones($withRelations = true)
    {
        $with = $withRelations ? [
            'countries',
            'states',
            'states.country'
        ] : [];
        $records = TaxZoneRecord::model()->with($with)->findAll(['order' => 't.name']);

        return TaxZone::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return TaxZone|null
     */
    public function getTaxZoneById($id)
    {
        $result = TaxZoneRecord::findOne($id);

        if ($result) {
            return new TaxZone($result);
        }

        return null;
    }

    /**
     * @param TaxZone $model
     * @param array   $countryIds
     * @param array   $stateIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveTaxZone(TaxZone $model, $countryIds, $stateIds)
    {
        if ($model->id) {
            $record = TaxZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No tax zone exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new TaxZoneRecord();
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
            $exist = CountryRecord::model()->exists($criteria);

            if (!$exist) {
                $model->addError('countries', 'Please select some countries');
            }
        } else {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $stateIds);
            $exist = StateRecord::model()->exists($criteria);

            if (!$exist) {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors()) {
            Db::beginStackedTransaction();
            try {
                // Save it!
                $record->save(false);

                // Now that we have a record ID, save it on the model
                $model->id = $record->id;

                // Clean out all old links
                TaxZoneCountryRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);
                TaxZoneStateRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);

                //saving new links
                if ($model->countryBased) {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'taxZoneId'];
                    $table = TaxZoneCountryRecord::model()->getTableName();
                } else {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'taxZoneId'];
                    $table = TaxZoneStateRecord::model()->getTableName();
                }
                Craft::$app->getDb()->createCommand()->insertAll($table, $cols, $rows);

                //If this was the default make all others not the default.
                if ($model->default) {
                    TaxZoneRecord::model()->updateAll(['default' => 0],
                        'id != ?', [$record->id]);
                }

                Db::commitStackedTransaction();
            } catch (\Exception $e) {
                Db::rollbackStackedTransaction();

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
        TaxZoneRecord::model()->deleteByPk($id);
    }

    /**
     * Returns all countries in a tax zone
     *
     * @param $taxZoneId
     *
     * @return array
     */
    public function getCountriesByTaxZoneId($taxZoneId)
    {
        if (!isset($this->_countriesByTaxZoneId) || !array_key_exists($taxZoneId, $this->_countriesByTaxZoneId)) {

            $results = TaxZoneCountryRecord::model()->with('country')->findAllByAttributes([
                'taxZoneId' => $taxZoneId
            ]);

            $countries = [];

            foreach ($results as $result) {
                $countries[] = new Country($result->country);
            }

            $this->_countriesByTaxZoneId[$taxZoneId] = $countries;
        }

        return $this->_countriesByTaxZoneId[$taxZoneId];
    }

    /**
     * Returns all states in a tax zone
     *
     * @param $taxZoneId
     *
     * @return array
     */
    public function getStatesByTaxZoneId($taxZoneId)
    {
        if (!isset($this->_statesByTaxZoneId) || !array_key_exists($taxZoneId, $this->_statesByTaxZoneId)) {

            $results = TaxZoneStateRecord::model()->with('state')->findAllByAttributes([
                'taxZoneId' => $taxZoneId
            ]);

            $states = [];
            foreach ($results as $result) {
                $states[] = new State($result->state);
            }

            $this->_statesByTaxZoneId[$taxZoneId] = $states;
        }

        return $this->_statesByTaxZoneId[$taxZoneId];
    }

}
