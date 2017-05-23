<?php
namespace craft\commerce\services;

use craft\commerce\helpers\Db;
use craft\commerce\models\Country;
use craft\commerce\models\ShippingZone;
use craft\commerce\models\State;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\commerce\records\ShippingZoneCountry as ShippingZoneCountryRecord;
use craft\commerce\records\ShippingZoneState as ShippingZoneStateRecord;
use craft\commerce\records\State as StateRecord;
use yii\base\Component;
use Craft;

/**
 * Shipping zone service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class ShippingZones extends Component
{
    /*
     * @var
     */
    private $_countriesByShippingZoneId;

    /*
     * @var
     */
    private $_statesByShippingZoneId;

    /**
     * @param bool $withRelations
     *
     * @return ShippingZone[]
     */
    public function getAllShippingZones($withRelations = true)
    {
        $with = $withRelations ? [
            'countries',
            'states',
            'states.country'
        ] : [];
        $records = ShippingZoneRecord::find()->with($with)->orderBy(['name'])->all();

        return ShippingZone::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return ShippingZone|null
     */
    public function getShippingZoneById($id)
    {
        $result = ShippingZoneRecord::findOne($id);

        if ($result) {
            return ShippingZone::populateModel($result);
        }

        return null;
    }

    /**
     * @param ShippingZone $model
     * @param array        $countryIds
     * @param array        $stateIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveShippingZone(ShippingZone $model, $countryIds, $stateIds)
    {
        if ($model->id) {
            $record = ShippingZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No shipping zone exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new ShippingZoneRecord();
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->countryBased = $model->countryBased;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating given ids
        if ($record->countryBased) {

            $exist = CountryRecord::find()->where(['id'=>$countryIds])->exists();

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
            Db::beginStackedTransaction();
            try {
                // Save it!
                $record->save(false);

                // Now that we have a record ID, save it on the model
                $model->id = $record->id;

                //deleting old links
                ShippingZoneCountryRecord::deleteAll(['shippingZoneId' => $record->id]);
                ShippingZoneStateRecord::deleteAll(['shippingZoneId' => $record->id]);

                //saving new links
                if ($model->countryBased) {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'shippingZoneId'];
                    $table = ShippingZoneCountryRecord::tableName();
                } else {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'shippingZoneId'];
                    $table = ShippingZoneStateRecord::tableName();
                }
                Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows);

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
    public function deleteShippingZoneById($id)
    {
        $record = ShippingZoneRecord::findOne($id);

        if($record)
        {
            $record->delete();
        }
    }

    /**
     * Returns all countries in a shipping zone
     *
     * @param $shippingZoneId
     *
     * @return array
     */
    public function getCountriesByShippingZoneId($shippingZoneId)
    {
        if (!isset($this->_countriesByShippingZoneId) || !array_key_exists($shippingZoneId, $this->_countriesByShippingZoneId)) {

            $results = ShippingZoneCountryRecord::find()->with('country')->where([
                'shippingZoneId' => $shippingZoneId
            ])->all();

            $countries = [];

            foreach ($results as $result) {
                $countries[] = Country::populateModel($result->country);
            }

            $this->_countriesByShippingZoneId[$shippingZoneId] = $countries;
        }

        return $this->_countriesByShippingZoneId[$shippingZoneId];
    }

    /**
     * Returns all states in a shipping zone
     *
     * @param $shippingZoneId
     *
     * @return array
     */
    public function getStatesByShippingZoneId($shippingZoneId)
    {
        if (!isset($this->_statesByShippingZoneId) || !array_key_exists($shippingZoneId, $this->_statesByShippingZoneId)) {

            $results = ShippingZoneStateRecord::model()->with('state')->findAllByAttributes([
                'shippingZoneId' => $shippingZoneId
            ]);

            $states = [];
            foreach ($results as $result) {
                $states[] = State::populateModel($result->state);
            }

            $this->_statesByShippingZoneId[$shippingZoneId] = $states;
        }

        return $this->_statesByShippingZoneId[$shippingZoneId];
    }

}
