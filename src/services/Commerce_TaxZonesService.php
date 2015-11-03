<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Tax zone service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_TaxZonesService extends BaseApplicationComponent
{
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
     * @param array $countriesIds
     * @param array $statesIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveTaxZone(Commerce_TaxZoneModel $model, $countriesIds, $statesIds)
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

        //remembering which links should be clean
        $deleteOldCountries = $deleteOldStates = false;
        if ($record->id) {
            if ($record->countryBased) {
                $deleteOldCountries = true;
            } else {
                $deleteOldStates = true;
            }
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
            $criteria->addInCondition('id', $countriesIds);
            $exist = Commerce_CountryRecord::model()->exists($criteria);

            if (!$exist) {
                $model->addError('countries', 'Please select some countries');
            }
        } else {
            $criteria = new \CDbCriteria();
            $criteria->addInCondition('id', $statesIds);
            $exist = Commerce_StateRecord::model()->exists($criteria);

            if (!$exist) {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors()) {
            CommerceDbHelper::beginStackedTransaction();
            try {
                // Save it!
                $record->save(false);

                // Now that we have a record ID, save it on the model
                $model->id = $record->id;

                //deleting old links
                if ($deleteOldCountries) {
                    Commerce_TaxZoneCountryRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);
                }

                if ($deleteOldStates) {
                    Commerce_TaxZoneStateRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);
                }

                //saving new links
                if ($model->countryBased) {
                    $rows = array_map(function ($id) use ($model) {
                        return [$id, $model->id];
                    }, $countriesIds);
                    $cols = ['countryId', 'taxZoneId'];
                    $table = Commerce_TaxZoneCountryRecord::model()->getTableName();
                } else {
                    $rows = array_map(function ($id) use ($model) {
                        return [$id, $model->id];
                    }, $statesIds);
                    $cols = ['stateId', 'taxZoneId'];
                    $table = Commerce_TaxZoneStateRecord::model()->getTableName();
                }
                craft()->db->createCommand()->insertAll($table, $cols, $rows);

                //If this was the default make all others not the default.
                if ($model->default) {
                    Commerce_TaxZoneRecord::model()->updateAll(['default' => 0],
                        'id != ?', [$record->id]);
                }

                CommerceDbHelper::commitStackedTransaction();
            } catch (\Exception $e) {
                CommerceDbHelper::rollbackStackedTransaction();

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
}
