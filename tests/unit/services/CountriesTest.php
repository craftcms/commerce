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
    private $_country;

    /**
     * @var int
     */
    private $_usCountryId = 236;

    public function testGetCountryById()
    {
        $country = $this->countries->getCountryById(999);
        $this->assertNull($country);

        $country = $this->countries->getCountryById($this->_usCountryId);
        $this->assertIsObject($country);
        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('United States', $country->name);
        $this->assertEquals($this->_usCountryId, $country->id);
    }

    public function testGetCountryByIso()
    {
        $country = $this->countries->getCountryByIso('XX');
        $this->assertNull($country);

        $country = $this->countries->getCountryByIso('US');
        $this->assertIsObject($country);
        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('United States', $country->name);
        $this->assertEquals($this->_usCountryId, $country->id);
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

        $this->_destroyCountry();
    }

    public function testGetAllCountriesAsList()
    {
        $countriesAsList = $this->countries->getAllCountriesAsList();

        $this->assertIsArray($countriesAsList);
        $this->assertArrayHasKey($this->_usCountryId, $countriesAsList);
        $this->assertSame('United States', $countriesAsList[$this->_usCountryId]);
    }

    public function testGetAllEnabledCountriesAsList()
    {
        $this->_createCountry();
        $enabledCountriesAsList = $this->countries->getAllEnabledCountriesAsList();

        $this->assertIsArray($enabledCountriesAsList);
        $this->assertArrayHasKey($this->_usCountryId, $enabledCountriesAsList);
        $this->assertSame('United States', $enabledCountriesAsList[$this->_usCountryId]);
        $this->assertArrayNotHasKey($this->_country->id, $enabledCountriesAsList);

        $this->_destroyCountry();
    }

    public function testGetAllEnabledCountries()
    {
        $this->_createCountry();
        $enabledCountries = $this->countries->getAllEnabledCountries();

        $this->assertIsArray($enabledCountries);
        $this->assertArrayHasKey($this->_usCountryId, $enabledCountries);
        $this->assertIsObject($enabledCountries[$this->_usCountryId]);
        $this->assertInstanceOf(Country::class, $enabledCountries[$this->_usCountryId]);
        $this->assertSame('United States', $enabledCountries[$this->_usCountryId]->name);
        $this->assertArrayNotHasKey($this->_country->id, $enabledCountries);

        $this->_destroyCountry();
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

        $this->_destroyCountry();
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

    /**
     *
     */
    public function _before()
    {
        parent::_before();

        $this->countries = Plugin::getInstance()->getCountries();
    }

    /**
     * @return bool
     * @throws \yii\base\Exception
     */
    private function _createCountry()
    {
        $this->_country = new Country();
        $this->_country->name = 'Krakozhia';
        $this->_country->iso = 'KA';
        $this->_country->enabled = false;

        return $this->countries->saveCountry($this->_country);
    }

    /**
     * @return bool
     */
    private function _destroyCountry()
    {
        return $this->countries->deleteCountryById($this->_country->id);
    }
}