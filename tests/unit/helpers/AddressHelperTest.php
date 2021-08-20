<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\helpers;

use Codeception\Test\Unit;
use craft\base\Model;
use craft\commerce\helpers\Address as AddressHelper;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\Plugin;
use craft\commerce\services\Addresses;
use craft\commerce\services\Countries;

/**
 * AddressHelperTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressHelperTest extends Unit
{
    
  private function _mockCountries()
  {
      $mockCountriesService = $this->make(Countries::class, [
          'getCountryById' => function($id) {
              $country = new Country();
              $country->iso = 'AU';

              return $country;
          },
          'getCountryByIso' => function($id) {
              $country = new Country();
              $country->iso = 'US';

              return $country;
          }
      ]);
      Plugin::getInstance()->set('countries', $mockCountriesService);
  }    
  
  public function testGetDefaultCountrySet()
  {
      $this->_mockCountries();
      
      $addressService = $this->make(Addresses::class, [
          'getStoreLocationAddress' => function() {
              $address = new Address();
              $address->id = 5;

              return $address;
          }
      ]);

      Plugin::getInstance()->set('addresses', $addressService);
      
      $defaultCountry = AddressHelper::getDefaultCountry();
      
      $this->assertEquals('AU', $defaultCountry->iso);
  }

    public function testGetDefaultCountryNotSet()
    {
        $this->_mockCountries();
        
        $addressService = $this->make(Addresses::class, [
            'getStoreLocationAddress' => function() {
                return new Address();
            }
        ]);

        Plugin::getInstance()->set('addresses', $addressService);

        $defaultCountry = AddressHelper::getDefaultCountry();

        $this->assertEquals(Address::DEFAULT_COUNTRY_ISO, $defaultCountry->iso);
    }
}
