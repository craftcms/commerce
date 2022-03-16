<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\Store;
use yii\base\InvalidConfigException;

/**
 * StoreTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreTest extends Unit
{
    /**
     * @param mixed $countries
     * @param bool $expectException
     * @param array $expected
     * @return void
     * @dataProvider setCountriesDataProvider
     */
    public function testSetCountries(mixed $countries, bool $expectException, array $expected): void
    {
        $store = new Store();
        if ($expectException) {
            $this->expectException(InvalidConfigException::class);
        }

        $store->setCountries($countries);

        self::assertEquals($expected, $store->getCountries());
    }

    /**
     * @param array $countries
     * @param array $expected
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getCountriesDataProvider
     */
    public function testGetCountriesList(array $countries, array $expected): void
    {
        $store = new Store();
        $store->setCountries($countries);

        self::assertEquals($expected, $store->getCountriesList());
    }

    /**
     * @param array $countries
     * @param array $expected
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getAdministrativeAreasListByCountryCodeDataProvider
     */
    public function testGetAdministrativeAreasListByCountryCode(array $countries, array $expected): void
    {
        $store = new Store();
        $store->setCountries($countries);

        self::assertEquals($expected, $store->getAdministrativeAreasListByCountryCode());
    }

    /**
     * @return array
     */
    public function getAdministrativeAreasListByCountryCodeDataProvider(): array
    {
        return [
            [['US', 'GB'], [
                'US' => [
                    'AL' => 'Alabama',
                    'AK' => 'Alaska',
                    'AS' => 'American Samoa',
                    'AZ' => 'Arizona',
                    'AR' => 'Arkansas',
                    'AA' => 'Armed Forces (AA)',
                    'AE' => 'Armed Forces (AE)',
                    'AP' => 'Armed Forces (AP)',
                    'CA' => 'California',
                    'CO' => 'Colorado',
                    'CT' => 'Connecticut',
                    'DE' => 'Delaware',
                    'DC' => 'District of Columbia',
                    'FL' => 'Florida',
                    'GA' => 'Georgia',
                    'GU' => 'Guam',
                    'HI' => 'Hawaii',
                    'ID' => 'Idaho',
                    'IL' => 'Illinois',
                    'IN' => 'Indiana',
                    'IA' => 'Iowa',
                    'KS' => 'Kansas',
                    'KY' => 'Kentucky',
                    'LA' => 'Louisiana',
                    'ME' => 'Maine',
                    'MH' => 'Marshall Islands',
                    'MD' => 'Maryland',
                    'MA' => 'Massachusetts',
                    'MI' => 'Michigan',
                    'FM' => 'Micronesia',
                    'MN' => 'Minnesota',
                    'MS' => 'Mississippi',
                    'MO' => 'Missouri',
                    'MT' => 'Montana',
                    'NE' => 'Nebraska',
                    'NV' => 'Nevada',
                    'NH' => 'New Hampshire',
                    'NJ' => 'New Jersey',
                    'NM' => 'New Mexico',
                    'NY' => 'New York',
                    'NC' => 'North Carolina',
                    'ND' => 'North Dakota',
                    'MP' => 'Northern Mariana Islands',
                    'OH' => 'Ohio',
                    'OK' => 'Oklahoma',
                    'OR' => 'Oregon',
                    'PW' => 'Palau',
                    'PA' => 'Pennsylvania',
                    'PR' => 'Puerto Rico',
                    'RI' => 'Rhode Island',
                    'SC' => 'South Carolina',
                    'SD' => 'South Dakota',
                    'TN' => 'Tennessee',
                    'TX' => 'Texas',
                    'UT' => 'Utah',
                    'VT' => 'Vermont',
                    'VI' => 'Virgin Islands',
                    'VA' => 'Virginia',
                    'WA' => 'Washington',
                    'WV' => 'West Virginia',
                    'WI' => 'Wisconsin',
                    'WY' => 'Wyoming',
                ],
                'GB' => [],
            ]],
            [['AU', 'US'], [
                'AU' => [
                    'ACT' => 'Australian Capital Territory',
                    'NSW' => 'New South Wales',
                    'NT' => 'Northern Territory',
                    'QLD' => 'Queensland',
                    'SA' => 'South Australia',
                    'TAS' => 'Tasmania',
                    'VIC' => 'Victoria',
                    'WA' => 'Western Australia',
                    'JBT' => 'Jervis Bay Territory',
                ],
                'US' => [
                    'AL' => 'Alabama',
                    'AK' => 'Alaska',
                    'AS' => 'American Samoa',
                    'AZ' => 'Arizona',
                    'AR' => 'Arkansas',
                    'AA' => 'Armed Forces (AA)',
                    'AE' => 'Armed Forces (AE)',
                    'AP' => 'Armed Forces (AP)',
                    'CA' => 'California',
                    'CO' => 'Colorado',
                    'CT' => 'Connecticut',
                    'DE' => 'Delaware',
                    'DC' => 'District of Columbia',
                    'FL' => 'Florida',
                    'GA' => 'Georgia',
                    'GU' => 'Guam',
                    'HI' => 'Hawaii',
                    'ID' => 'Idaho',
                    'IL' => 'Illinois',
                    'IN' => 'Indiana',
                    'IA' => 'Iowa',
                    'KS' => 'Kansas',
                    'KY' => 'Kentucky',
                    'LA' => 'Louisiana',
                    'ME' => 'Maine',
                    'MH' => 'Marshall Islands',
                    'MD' => 'Maryland',
                    'MA' => 'Massachusetts',
                    'MI' => 'Michigan',
                    'FM' => 'Micronesia',
                    'MN' => 'Minnesota',
                    'MS' => 'Mississippi',
                    'MO' => 'Missouri',
                    'MT' => 'Montana',
                    'NE' => 'Nebraska',
                    'NV' => 'Nevada',
                    'NH' => 'New Hampshire',
                    'NJ' => 'New Jersey',
                    'NM' => 'New Mexico',
                    'NY' => 'New York',
                    'NC' => 'North Carolina',
                    'ND' => 'North Dakota',
                    'MP' => 'Northern Mariana Islands',
                    'OH' => 'Ohio',
                    'OK' => 'Oklahoma',
                    'OR' => 'Oregon',
                    'PW' => 'Palau',
                    'PA' => 'Pennsylvania',
                    'PR' => 'Puerto Rico',
                    'RI' => 'Rhode Island',
                    'SC' => 'South Carolina',
                    'SD' => 'South Dakota',
                    'TN' => 'Tennessee',
                    'TX' => 'Texas',
                    'UT' => 'Utah',
                    'VT' => 'Vermont',
                    'VI' => 'Virgin Islands',
                    'VA' => 'Virginia',
                    'WA' => 'Washington',
                    'WV' => 'West Virginia',
                    'WI' => 'Wisconsin',
                    'WY' => 'Wyoming',
                ],
            ]],
            [[], []],
        ];
    }

    /**
     * @return array
     */
    public function getCountriesDataProvider(): array
    {
        return [
            [['US', 'GB', 'LV'], ['LV' => 'Latvia', 'GB' => 'United Kingdom', 'US' => 'United States']],
            [['US', 'GB'], ['GB' => 'United Kingdom', 'US' => 'United States']],
            [['US'], ['US' => 'United States']],
            [['XX'], []],
            [[], []],
        ];
    }

    /**
     * @return array[]
     */
    public function setCountriesDataProvider(): array
    {
        return [
            [json_encode(['US', 'CA']), false, ['US', 'CA']],
            ['US', true, []],
            [['US', 'GB'], false, ['US', 'GB']],
        ];
    }
}