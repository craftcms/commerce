<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\Plugin;
use craft\commerce\services\Countries;

/**
 * AddressFormatTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressFormatTest extends Unit
{
    /**
     * @throws \yii\base\InvalidConfigException
     */
   public function testAddressFormat()
   {
       $mockCountriesService = $this->make(Countries::class, [
           'getCountryById' => function($id) {
               $country = new Country();
               $country->iso = 'US';
               
               return $country;
           }
       ]);

       Plugin::getInstance()->set('countries', $mockCountriesService);

       $expectedFormat = trim("
%givenName %familyName
%organization
%addressLine1
%addressLine2
%locality, %administrativeArea %postalCode
        ");
       $expectedFormat = preg_replace('/[ \t]+/', ' ', preg_replace('/[\r\n]+/', "\n", $expectedFormat));
       $address = new Address();
       $address->countryId = 1;
       $format = $address->getAddressFormat();

       self::assertEquals($expectedFormat, $format);
   }
}