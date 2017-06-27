<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\Country;
use craft\commerce\records\Country as CountryRecord;
use craft\db\Query;
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
     * @var Country[]
     */
    private $_countriesById = [];

    /**
     * @var Country[]
     */
    private $_countriesByTaxZoneId;

    /**
     * @param int $id
     *
     * @return Country|null
     */
    public function getCountryById($id)
    {
        if (!isset($this->_countriesById[$id])) {
            $row = $this->_createCountryQuery()
                ->where(['id' => $id])
                ->one();

            $this->_countriesById[$id] = $row ? new Country($row) : null;
        }

        return $this->_countriesById[$id];
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
        $results = $this->_createCountryQuery()
            ->all();

        $countries = [];

        foreach ($results as $result) {
            $country = new Country($result);
            $countries[$country->id] = $country;
        }

        return $countries;
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
        if (null === $this->_countriesByTaxZoneId) {
            $this->_countriesByTaxZoneId = [];
            $results = $this->_createCountryQuery()
                ->innerJoin('{{%commerce_taxzone_countries}} taxZoneCountries', '[[countries.id]] = [[taxZoneCountries.countryId]]')
                ->all();
            $countries = [];

            foreach ($results as $result) {
                $countries[] = new Country($result);
            }

            $this->_countriesByTaxZoneId[$taxZoneId] = $countries;
        }

        return $this->_countriesByTaxZoneId[$taxZoneId] ?? [];
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
        // Nuke the asset volume.
        Craft::$app->getDb()->createCommand()
            ->delete('{{%commerce_countries}}', ['id' => $id])
            ->execute();
    }


    // Private methods
    // =========================================================================
    /**
     * Returns a Query object prepped for retrieving Countries.
     *
     * @return Query
     */    private function _createCountryQuery(): Query
    {

        return (new Query())
            ->select([
                'countries.id',
                'countries.name',
                'countries.iso',
                'countries.stateRequired'
            ])
            ->from(['{{%commerce_countries}} countries'])
            ->orderBy(['name' => SORT_ASC]);
    }
}
