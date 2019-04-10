<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
 * @property Country[]|array $allCountries an array of all countries
 * @property array $allCountriesAsList
 * @property Country[]|array $allCountriesListData all country names, indexed by ID
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
     * Returns a country by its ID.
     *
     * @param int $id the country's ID
     * @return Country|null
     */
    public function getCountryById(int $id)
    {
        if (isset($this->_countriesById[$id])) {
            return $this->_countriesById[$id];
        }

        if ($this->_fetchedAllCountries) {
            return null;
        }

        $result = $this->_createCountryQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_countriesById[$id] = new Country($result);
    }

    /**
     * Returns a country by its ISO code.
     *
     * @param string $iso the country's ISO code
     * @return Country|null
     */
    public function getCountryByIso(string $iso)
    {
        $result = $this->_createCountryQuery()
            ->where(['iso' => $iso])
            ->one();

        return $result ? new Country($result) : null;
    }

    /**
     * Returns all country names, indexed by ID.
     *
     * @return array
     * @deprecated as of 2.0
     */
    public function getAllCountriesListData(): array
    {
        Craft::$app->getDeprecator()->log('Countries::getAllCountriesListData()', 'Countries::getAllCountriesListData() has been deprecated. Use Countries::getAllCountriesAsList() instead');

        return $this->getAllCountriesAsList();
    }

    /**
     * Returns all country names, indexed by ID.
     *
     * @return array
     */
    public function getAllCountriesAsList(): array
    {
        $countries = $this->getAllCountries();

        return ArrayHelper::map($countries, 'id', 'name');
    }

    /**
     * Returns an array of all countries.
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
     * Returns all countries in a tax zone, per the tax zone's ID.
     *
     * @param int $taxZoneId the tax zone's ID
     * @return Country[] an array of countries in the matched tax zone
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
     * Returns all countries in a shipping zone, per the shipping zone's ID.
     *
     * @param int $shippingZoneId the shipping zone's ID
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
     * Saves a country.
     *
     * @param Country $country The country to be saved.
     * @param bool $runValidation should we validate this country before saving.
     * @return bool Whether the country was saved successfully.
     * @throws Exception if the country does not exist.
     */
    public function saveCountry(Country $country, bool $runValidation = true): bool
    {
        if ($country->id) {
            $record = CountryRecord::findOne($country->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No country exists with the ID “{id}”', ['id' => $country->id]));
            }
        } else {
            $record = new CountryRecord();
        }

        if ($runValidation && !$country->validate()) {
            Craft::info('Country not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $country->name;
        $record->iso = strtoupper($country->iso);
        $record->isStateRequired = $country->isStateRequired;


        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $country->id = $record->id;

        return true;
    }

    /**
     * Deletes a country by its ID.
     *
     * @param int $id the country's ID
     * @return bool whether the country was deleted successfully
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
                'countries.isStateRequired'
            ])
            ->from(['{{%commerce_countries}} countries'])
            ->orderBy(['name' => SORT_ASC]);
    }
}
