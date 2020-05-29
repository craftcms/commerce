<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\models\Country;
use craft\commerce\Plugin;
use craft\commerce\services\Countries;
use craft\db\Query;
use UnitTester;

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
    protected $tester;

    /**
     * @var Countries $countries
     */
    protected $countries;

    /**
     * @var null|Country
     */
    private $_country = null;

    public function testGetCountryById()
    {
        $country = $this->countries->getCountryById(999);
        $this->assertNull($country);

        $country = $this->countries->getCountryById(233);
        $this->assertIsObject($country);
        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('United States', $country->name);
        $this->assertEquals(233, $country->id);
    }

    public function testGetCountryByIso()
    {
        $country = $this->countries->getCountryByIso('XX');
        $this->assertNull($country);

        $country = $this->countries->getCountryByIso('US');
        $this->assertIsObject($country);
        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('United States', $country->name);
        $this->assertEquals(233, $country->id);
    }

    public function testGetAllCountries()
    {
        $countriesCount = (new Query())
            ->from(Table::COUNTRIES)
            ->count();

        $countries = $this->countries->getAllCountries();

        $this->assertCount($countriesCount, $countries);
    }

    public function testSaveCountry()
    {
        // Force memoization for testing
        $this->countries->getAllCountries();

        $result = $this->_createCountry();
        $this->assertTrue($result);

        // Test it is in the DB
        $exists = (new Query())
            ->from(Table::COUNTRIES)
            ->where(['iso' => 'KA'])
            ->exists();
        $this->assertTrue($exists);

        // Test we can retrieve new data by ID and ISO
        $byId = $this->countries->getCountryById($this->_country->id);
        $this->assertIsObject($byId);
        $this->assertInstanceOf(Country::class, $byId);
        $this->assertSame($this->_country->name, $byId->name);

        $byIso = $this->countries->getCountryByIso($this->_country->iso);
        $this->assertIsObject($byIso);
        $this->assertInstanceOf(Country::class, $byIso);
        $this->assertSame($this->_country->name, $byIso->name);
    }

    public function testGetAllCountriesAsList()
    {
        $countriesAsList = $this->countries->getAllCountriesAsList();

        $this->assertIsArray($countriesAsList);
        $this->assertArrayHasKey('233', $countriesAsList);
        $this->assertSame('United States', $countriesAsList[233]);
    }

    public function testGetAllEnabledCountriesAsList()
    {
        $this->_createCountry();
        $enabledCountriesAsList = $this->countries->getAllEnabledCountriesAsList();

        $this->assertIsArray($enabledCountriesAsList);
        $this->assertArrayHasKey('233', $enabledCountriesAsList);
        $this->assertSame('United States', $enabledCountriesAsList[233]);
        $this->assertArrayNotHasKey($this->_country->id, $enabledCountriesAsList);
    }

    public function testGetAllEnabledCountries()
    {
        $this->_createCountry();
        $enabledCountries = $this->countries->getAllEnabledCountries();

        $this->assertIsArray($enabledCountries);
        $this->assertArrayHasKey('233', $enabledCountries);
        $this->assertIsObject($enabledCountries[233]);
        $this->assertInstanceOf(Country::class, $enabledCountries[233]);
        $this->assertSame('United States', $enabledCountries[233]->name);
        $this->assertArrayNotHasKey($this->_country->id, $enabledCountries);
    }

    public function testGetCountriesByTaxZoneId()
    {
        // TODO after zone fixtures
    }

    public function testGetCountriesByShippingZoneId()
    {
        // TODO after zone fixtures
    }

    public function testDeleteCountryById()
    {
        $this->_createCountry();
        $id = $this->_country->id;

        // Force memoization to test deletion
        $this->countries->getAllCountries();

        $result = $this->countries->deleteCountryById($id);
        $this->assertTrue($result);

        // Check DB
        $exists = (new Query())
            ->from(Table::COUNTRIES)
            ->where(['id' => $id])
            ->exists();
        $this->assertFalse($exists);

        $byId = $this->countries->getCountryById($id);
        $this->assertNull($byId);
    }

    public function testReorderCountries()
    {
        $countriesAsList = $this->countries->getAllCountriesAsList();
        $countriesOrder = array_keys($countriesAsList);
        $countriesOrder = array_reverse($countriesOrder);

        $result = $this->countries->reorderCountries($countriesOrder);
        $this->assertTrue($result);

        $dbCountriesOrder = (new Query())
            ->from(Table::COUNTRIES)
            ->select(['id'])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->column();
        $this->assertEquals($countriesOrder, $dbCountriesOrder);

        $countriesAsList = $this->countries->getAllCountriesAsList();
        $this->assertEquals($countriesOrder, array_keys($countriesAsList));
    }

    public function _before()
    {
        parent::_before();

        $this->countries = Plugin::getInstance()->getCountries();
    }

    private function _createCountry()
    {
        $this->_country = new Country();
        $this->_country->name = 'Krakozhia';
        $this->_country->iso = 'KA';
        $this->_country->enabled = false;

        return $this->countries->saveCountry($this->_country);
    }
}