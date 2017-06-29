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
     * @var bool
     */
    private $_fetchedAllCountries = false;

    /**
     * @var Country[]
     */
    private $_countriesById = [];

    /**
     * @var Country[]
     */
    private $_countriesByTaxZoneId = [];

  /**
     * @var Country[]
     */
    private $_countriesByShippingZoneId = [];

    /**
     * @param int $id
     *
     * @return Country|null
     */
    public function getCountryById($id)
    {
        if (isset($this->_countriesById[$id])) {
            return $this->_countriesById[$id];
        }

        if ($this->_fetchedAllCountries) {
            return null;
        }

        $row = $this->_createCountryQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        return $this->_countriesById[$id] = new Country($row);
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
        if (!$this->_fetchedAllCountries) {
            $this->_fetchedAllCountries = true;
            $results = $this->_createCountryQuery()->all();

            foreach ($results as $row ) {
                $this->_countriesById[$row['id']] = new Country($row);
            }
        }

        return $this->_countriesById;
    }

    /**
     * Returns all countries in a tax zone
     *
     * @param $taxZoneId
     *
     * @return array
     */
    public function getCountriesByTaxZoneId($taxZoneId): array
    {
        if (!isset($this->_countriesByTaxZoneId[$taxZoneId])) {
            $results = $this->_createCountryQuery()
                ->innerJoin('{{%commerce_taxzone_countries}} taxZoneCountries', '[[countries.id]] = [[taxZoneCountries.countryId]]')
                ->where(['taxZoneCountries.taxZoneId' => $taxZoneId])
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
     * Returns all countries in a shipping zone
     *
     * @param $shippingZoneId
     *
     * @return array
     */
    public function getCountriesByShippingZoneId($shippingZoneId): array
    {
        if (!isset($this->_countriesByShippingZoneId[$shippingZoneId])) {
            $results = $this->_createCountryQuery()
                ->innerJoin('{{%commerce_shippingzone_countries}} shippingZoneCountries', '[[countries.id]] = [[shippingZoneCountries.countryId]]')
                ->where(['shippingZoneCountries.shippingZoneId' => $shippingZoneId])
                ->all();
            $countries = [];

            foreach ($results as $result) {
                $countries[] = new Country($result);
            }

            $this->_countriesByShippingZoneId[$shippingZoneId] = $countries;
        }

        return $this->_countriesByShippingZoneId[$shippingZoneId] ?? [];
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
                throw new Exception(Craft::t('commerce', 'No country exists with the ID “{id}”', ['id' => $model->id]));
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
     * @param int $id
     *
     * @return bool
     */
    public function deleteCountryById($id): bool
    {
        $record = CountryRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
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
