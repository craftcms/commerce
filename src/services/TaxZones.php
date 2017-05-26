<?php

namespace craft\commerce\services;

use Craft;
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
        $records = TaxZoneRecord::find()->with($with)->orderBy('name')->all();

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
            $exist = CountryRecord::find()->where(['id' => $countryIds])->exists();

            if (!$exist) {
                $model->addError('countries', 'Please select some countries');
            }
        } else {
            $exist = StateRecord::find()->where(['id' => $stateIds])->exists();

            if (!$exist) {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors()) {

            $db = Craft::$app->getDb();
            $transaction = $db->beginTransaction();

            try {
                // Save it!
                $record->save(false);

                // Now that we have a record ID, save it on the model
                $model->id = $record->id;

                // Clean out all old links
                TaxZoneCountryRecord::deleteAll(['taxZoneId' => $record->id]);
                TaxZoneStateRecord::deleteAll(['taxZoneId' => $record->id]);

                //saving new links
                if ($model->countryBased) {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'taxZoneId'];
                    $table = TaxZoneCountryRecord::tableName();
                } else {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'taxZoneId'];
                    $table = TaxZoneStateRecord::tableName();
                }
                Craft::$app->getDb()->createCommand()->insertAll($table, $cols, $rows);

                //If this was the default make all others not the default.
                if ($model->default) {
                    TaxZoneRecord::updateAll(['default' => 0], 'id != ?', [$record->id]);
                }

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();

                throw $e;
            }

            return true;
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return bool|false|int
     */
    public function deleteTaxZoneById($id)
    {
        $record = TaxZoneRecord::findOne($id);

        if ($record) {
            return $record->delete();
        }

        return false;
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
        if (null === $this->_countriesByTaxZoneId || !array_key_exists($taxZoneId, $this->_countriesByTaxZoneId)) {

            $results = TaxZoneCountryRecord::find()->with('country')->where([
                'taxZoneId' => $taxZoneId
            ])->all();

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
        if (null === $this->_statesByTaxZoneId || !array_key_exists($taxZoneId, $this->_statesByTaxZoneId)) {

            $results = TaxZoneStateRecord::find()->with('state')->where([
                'taxZoneId' => $taxZoneId
            ])->all();

            $states = [];
            foreach ($results as $result) {
                $states[] = new State($result->state);
            }

            $this->_statesByTaxZoneId[$taxZoneId] = $states;
        }

        return $this->_statesByTaxZoneId[$taxZoneId];
    }

}
