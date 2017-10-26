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
 * @property Country[]|array $allCountriesListData
 * @property Country[]|array $allCountries
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
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllCountries = false;

    /**
     * @var Country[]
     */
    private $_countriesById = [];

    /**
     * @var Country[][]
     */
    private $_countriesByTaxZoneId = [];

    /**
     * @var Country[][]
     */
    private $_countriesByShippingZoneId = [];

    // Public Methods
    // =========================================================================

    /**
     * Get a country by it's id.
     *
     * @param int $id The country id.
     *
     * @return Country|null The matched country or null if not found.
     *
     */
    public function getCountryById(int $id)
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
     * Get all countries as an array of id => name.
     *
     * @return Country[] Array of countries indexed by id.
     */
    public function getAllCountriesListData(): array
    {
        $countries = $this->getAllCountries();

        return ArrayHelper::map($countries, 'id', 'name');
    }

    /**
     * Get an array of all countries.
     *
     * @return Country[] An array of all countries.
     */
    public function getAllCountries(): array
    {
        if (!$this->_fetchedAllCountries) {
            $this->_fetchedAllCountries = true;
            $results = $this->_createCountryQuery()->all();

            foreach ($results as $row) {
                $this->_countriesById[$row['id']] = new Country($row);
            }
        }

        return $this->_countriesById;
    }

    /**
     * Returns all countries in a tax zone.
     *
     * @param int $taxZoneId Tax zone id.
     *
     * @return Country[] An array of countries in the matched tax zone.
     */
    public function getCountriesByTaxZoneId(int $taxZoneId): array
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

        return $this->_countriesByTaxZoneId[$taxZoneId];
    }

    /**
     * Returns all countries in a shipping zone.
     *
     * @param int $shippingZoneId Shipping zone id.
     *
     * @return Country[] An array of countries in the matched shipping zone.
     */
    public function getCountriesByShippingZoneId(int $shippingZoneId): array
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

        return $this->_countriesByShippingZoneId[$shippingZoneId];
    }

    /**
     * Save a country.
     *
     * @param Country $country The country to be saved.
     *
     * @return bool Whether the country was saved successfully.
     * @throws Exception if the country does not exist.
     */
    public function saveCountry(Country $country): bool
    {
        if ($country->id) {
            $record = CountryRecord::findOne($country->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No country exists with the ID “{id}”', ['id' => $country->id]));
            }
        } else {
            $record = new CountryRecord();
        }

        $record->name = $country->name;
        $record->iso = strtoupper($country->iso);
        $record->stateRequired = $country->stateRequired;

        $record->validate();
        $country->addErrors($record->getErrors());

        if ($country->hasErrors()) {
            return false;
        }

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $country->id = $record->id;

        return true;
    }

    /**
     * Delete a country by it's id.
     *
     * @param int $id The id of the country.
     *
     * @return bool Whether the country was deleted successfully.
     */
    public function deleteCountryById(int $id): bool
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
     * @return Query The query object.
     */
    private function _createCountryQuery(): Query
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
