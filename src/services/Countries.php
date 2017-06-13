<?php

namespace craft\commerce\services;

use craft\commerce\models\Country;
use craft\commerce\records\Country as CountryRecord;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Country service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Countries extends Component
{
    /**
     * @param int $id
     *
     * @return Country|null
     */
    public function getCountryById($id)
    {
        $result = CountryRecord::findOne($id);

        if ($result) {
            return $this->_createCountryFromCountryRecord($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return Country|null
     */
    public function getCountryByAttributes(array $attr)
    {
        $result = CountryRecord::find()->where($attr)->one();

        if ($result) {
            return $this->_createCountryFromCountryRecord($result);
        }

        return null;
    }

    /**
     * Simple list for using in forms
     *
     * @return array [id => name]
     */
    public function getAllCountriesListData(): array
    {
        $countries = $this->getAllCountries();

        return ArrayHelper::map($countries, 'id', 'name');
    }

    /**
     * @return Country[]
     */
    public function getAllCountries(): array
    {
        $records = CountryRecord::find()->orderBy('name')->all();

        return ArrayHelper::map($records, 'id', function($record){
           return $this->_createCountryFromCountryRecord($record);
        });
    }

    /**
     * @param Country $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveCountry(Country $model): bool
    {
        if ($model->id) {
            $record = CountryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No country exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new CountryRecord();
        }

        $record->name = $model->name;
        $record->iso = strtoupper($model->iso);
        $record->stateRequired = $model->stateRequired;

        $record->validate();
        $model->addErrors($record->getErrors());

        if ($model->hasErrors()) {
            return false;
        }

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * @param $id
     */
    public function deleteCountryById($id)
    {
        $country = CountryRecord::findOne($id);
        if ($country) {
            $country->delete();
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates a Country with attributes from a CountryRecord.
     *
     * @param CountryRecord|null $record
     *
     * @return Country|null
     */
    private function _createCountryFromCountryRecord(CountryRecord $record = null)
    {
        if (!$record) {
            return null;
        }

        return new Country($record->toArray([
            'id',
            'name',
            'iso',
            'stateRequired'
        ]));
    }
}
