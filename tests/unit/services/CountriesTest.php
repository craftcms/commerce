<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\models\Country;
use craft\commerce\Plugin;
use craft\commerce\services\Countries;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use UnitTester;
use yii\base\Exception;
use yii\db\StaleObjectException;

/**
 * CountriesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class CountriesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Countries $countries
     */
    protected Countries $countries;

    /**
     * @var null|Country
     */
    private ?Country $_country;

    /**
     * @var int
     */
    private int $_usCountryId = 236;

    /**
     *
     */
    public function testGetCountryById(): void
    {
        $country = $this->countries->getCountryById(999);
        self::assertNull($country);

        $country = $this->countries->getCountryById($this->_usCountryId);
        self::assertIsObject($country);
        self::assertInstanceOf(Country::class, $country);
        self::assertSame('United States', $country->name);
        self::assertEquals($this->_usCountryId, $country->id);
    }

    /**
     *
     */
    public function testGetCountryByIso(): void
    {
        $country = $this->countries->getCountryByIso('XX');
        self::assertNull($country);

        $country = $this->countries->getCountryByIso('US');
        self::assertIsObject($country);
        self::assertInstanceOf(Country::class, $country);
        self::assertSame('United States', $country->name);
        self::assertEquals($this->_usCountryId, $country->id);
    }

    /**
     *
     */
    public function testGetAllCountries(): void
    {
        $countriesCount = (new Query())
            ->from(Table::COUNTRIES)
            ->count();

        $countries = $this->countries->getAllCountries();

        self::assertCount($countriesCount, $countries);
    }

    /**
     * @throws Exception
     */
    public function testSaveCountry(): void
    {
        // Force memoization for testing
        $this->countries->getAllCountries();

        $result = $this->_createCountry();
        self::assertTrue($result);

        // Test it is in the DB
        $exists = (new Query())
            ->from(Table::COUNTRIES)
            ->where(['iso' => 'KA'])
            ->exists();
        self::assertTrue($exists);

        // Test we can retrieve new data by ID and ISO
        $byId = $this->countries->getCountryById($this->_country->id);
        self::assertIsObject($byId);
        self::assertInstanceOf(Country::class, $byId);
        self::assertSame($this->_country->name, $byId->name);

        $byIso = $this->countries->getCountryByIso($this->_country->iso);
        self::assertIsObject($byIso);
        self::assertInstanceOf(Country::class, $byIso);
        self::assertSame($this->_country->name, $byIso->name);

        $this->_destroyCountry();
    }

    /**
     *
     */
    public function testGetAllCountriesAsList(): void
    {
        $countriesAsList = $this->countries->getAllCountriesAsList();

        self::assertIsArray($countriesAsList);
        self::assertArrayHasKey($this->_usCountryId, $countriesAsList);
        self::assertSame('United States', $countriesAsList[$this->_usCountryId]);
    }

    /**
     * @throws Exception
     */
    public function testGetAllEnabledCountriesAsList(): void
    {
        $this->_createCountry();
        $enabledCountriesAsList = $this->countries->getAllEnabledCountriesAsList();

        self::assertIsArray($enabledCountriesAsList);
        self::assertArrayHasKey($this->_usCountryId, $enabledCountriesAsList);
        self::assertSame('United States', $enabledCountriesAsList[$this->_usCountryId]);
        self::assertArrayNotHasKey($this->_country->id, $enabledCountriesAsList);

        $this->_destroyCountry();
    }

    /**
     * @throws Exception
     */
    public function testGetAllEnabledCountries(): void
    {
        $this->_createCountry();
        $enabledCountries = $this->countries->getAllEnabledCountries();

        self::assertIsArray($enabledCountries);
        self::assertArrayHasKey($this->_usCountryId, $enabledCountries);
        self::assertIsObject($enabledCountries[$this->_usCountryId]);
        self::assertInstanceOf(Country::class, $enabledCountries[$this->_usCountryId]);
        self::assertSame('United States', $enabledCountries[$this->_usCountryId]->name);
        self::assertArrayNotHasKey($this->_country->id, $enabledCountries);

        $this->_destroyCountry();
    }

    /**
     * @throws Exception
     */
    public function testDeleteCountryById(): void
    {
        $this->_createCountry();
        $id = $this->_country->id;

        // Force memoization to test deletion
        $this->countries->getAllCountries();

        $result = $this->countries->deleteCountryById($id);
        self::assertTrue($result);

        // Check DB
        $exists = (new Query())
            ->from(Table::COUNTRIES)
            ->where(['id' => $id])
            ->exists();
        self::assertFalse($exists);

        $byId = $this->countries->getCountryById($id);
        self::assertNull($byId);

        $this->_destroyCountry();
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testReorderCountries(): void
    {
        $countriesAsList = $this->countries->getAllCountriesAsList();
        $countriesOrder = array_keys($countriesAsList);
        $countriesOrder = array_reverse($countriesOrder);

        $result = $this->countries->reorderCountries($countriesOrder);
        self::assertTrue($result);

        $dbCountriesOrder = (new Query())
            ->from(Table::COUNTRIES)
            ->select(['id'])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->column();
        self::assertEquals($countriesOrder, $dbCountriesOrder);

        $countriesAsList = $this->countries->getAllCountriesAsList();
        self::assertEquals($countriesOrder, array_keys($countriesAsList));
    }

    /**
     * @throws Exception
     */
    public function testMemoization(): void
    {
        $countries = $this->countries->getAllCountries();
        $name = 'New Country Name';

        /** @var Country $country */
        $country = ArrayHelper::firstValue($countries);
        $country->name = $name;

        $this->countries->saveCountry($country);

        // Retrieve the countries again
        $countries = $this->countries->getAllCountries();
        $country = ArrayHelper::firstValue($countries);
        self::assertSame($name, $country->name);
    }

    /**
     *
     */
    public function _before(): void
    {
        parent::_before();

        $this->countries = Plugin::getInstance()->getCountries();
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function _createCountry(): bool
    {
        $this->_country = new Country();
        $this->_country->name = 'Krakozhia';
        $this->_country->iso = 'KA';
        $this->_country->enabled = false;

        return $this->countries->saveCountry($this->_country);
    }

    /**
     * @return void
     * @throws \Throwable
     * @throws StaleObjectException
     */
    private function _destroyCountry(): void
    {
        $this->countries->deleteCountryById($this->_country->id);
    }
}