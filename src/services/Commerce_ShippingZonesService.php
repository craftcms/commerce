<?php
namespace Craft;


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
class Commerce_ShippingZonesService extends BaseApplicationComponent
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
     * @return Commerce_ShippingZoneModel[]
     */
    public function getAllShippingZones($withRelations = true)
    {
        $with = $withRelations ? [
            'countries',
            'states',
            'states.country'
        ] : [];
        $records = Commerce_ShippingZoneRecord::model()->with($with)->findAll(['order' => 't.name']);

        return Commerce_ShippingZoneModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Commerce_ShippingZoneModel|null
     */
    public function getShippingZoneById($id)
    {
        $result = Commerce_ShippingZoneRecord::model()->findById($id);

        if ($result)
        {
            return Commerce_ShippingZoneModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param Commerce_ShippingZoneModel $model
     * @param array                      $countryIds
     * @param array                      $stateIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveShippingZone(Commerce_ShippingZoneModel $model, $countryIds, $stateIds)
    {
        if ($model->id)
        {
            $record = Commerce_ShippingZoneRecord::model()->findById($model->id);

            if (!$record)
            {
                throw new Exception(Craft::t('No shipping zone exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        }
        else
        {
            $record = new Commerce_ShippingZoneRecord();
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->countryBased = $model->countryBased;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating given ids
        if ($record->countryBased)
        {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $countryIds);
            $exist = Commerce_CountryRecord::model()->exists($criteria);

            if (!$exist)
            {
                $model->addError('countries', 'Please select some countries');
            }
        }
        else
        {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $stateIds);
            $exist = Commerce_StateRecord::model()->exists($criteria);

            if (!$exist)
            {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors())
        {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try
            {
                // Save it!
                $record->save(false);

                // Now that we have a record ID, save it on the model
                $model->id = $record->id;

                //deleting old links
                Commerce_ShippingZoneCountryRecord::model()->deleteAllByAttributes(['shippingZoneId' => $record->id]);
                Commerce_ShippingZoneStateRecord::model()->deleteAllByAttributes(['shippingZoneId' => $record->id]);

                //saving new links
                if ($model->countryBased)
                {
                    $rows = array_map(function ($id) use ($model)
                    {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'shippingZoneId'];
                    $table = Commerce_ShippingZoneCountryRecord::model()->getTableName();
                }
                else
                {
                    $rows = array_map(function ($id) use ($model)
                    {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'shippingZoneId'];
                    $table = Commerce_ShippingZoneStateRecord::model()->getTableName();
                }
                craft()->db->createCommand()->insertAll($table, $cols, $rows);

                if ($transaction !== null)
                {
                    $transaction->commit();
                }
            }
            catch (\Exception $e)
            {
                if ($transaction !== null)
                {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @param int $id
     */
    public function deleteShippingZoneById($id)
    {
        Commerce_ShippingZoneRecord::model()->deleteByPk($id);
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
        if (!isset($this->_countriesByShippingZoneId) || !array_key_exists($shippingZoneId, $this->_countriesByShippingZoneId))
        {

            $results = Commerce_ShippingZoneCountryRecord::model()->with('country')->findAllByAttributes([
                'shippingZoneId' => $shippingZoneId
            ]);

            $countries = [];

            foreach ($results as $result)
            {
                $countries[] = Commerce_CountryModel::populateModel($result->country);
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
        if (!isset($this->_statesByShippingZoneId) || !array_key_exists($shippingZoneId, $this->_statesByShippingZoneId))
        {

            $results = Commerce_ShippingZoneStateRecord::model()->with('state')->findAllByAttributes([
                'shippingZoneId' => $shippingZoneId
            ]);

            $states = [];
            foreach ($results as $result)
            {
                $states[] = Commerce_StateModel::populateModel($result->state);
            }

            $this->_statesByShippingZoneId[$shippingZoneId] = $states;
        }

        return $this->_statesByShippingZoneId[$shippingZoneId];
    }

}
