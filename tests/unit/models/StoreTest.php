<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\StoreSettings;
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
        $store = new StoreSettings();
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
        $store = new StoreSettings();
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
        $store = new StoreSettings();
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
                'GB' => [
                    "Antrim and Newtownabbey" => "Antrim and Newtownabbey",
                    "Ards and North Down" => "Ards and North Down",
                    "Armagh City, Banbridge and Craigavon" => "Armagh City, Banbridge and Craigavon",
                    "Barking and Dagenham" => "Barking and Dagenham",
                    "Barnet" => "Barnet",
                    "Barnsley" => "Barnsley",
                    "Bath and North East Somerset" => "Bath and North East Somerset",
                    "Bedford" => "Bedford",
                    "Belfast City" => "Belfast City",
                    "Bexley" => "Bexley",
                    "Birmingham" => "Birmingham",
                    "Blackburn with Darwen" => "Blackburn with Darwen",
                    "Blackpool" => "Blackpool",
                    "Blaenau Gwent" => "Blaenau Gwent",
                    "Bolton" => "Bolton",
                    "Bournemouth, Christchurch and Poole" => "Bournemouth, Christchurch and Poole",
                    "Bracknell Forest" => "Bracknell Forest",
                    "Bradford" => "Bradford",
                    "Brent" => "Brent",
                    "Bridgend" => "Bridgend",
                    "Brighton and Hove" => "Brighton and Hove",
                    "Bristol, City of" => "Bristol, City of",
                    "Bromley" => "Bromley",
                    "Buckinghamshire" => "Buckinghamshire",
                    "Bury" => "Bury",
                    "Caerphilly" => "Caerphilly",
                    "Calderdale" => "Calderdale",
                    "Cambridgeshire" => "Cambridgeshire",
                    "Camden" => "Camden",
                    "Cardiff" => "Cardiff",
                    "Carmarthenshire" => "Carmarthenshire",
                    "Causeway Coast and Glens" => "Causeway Coast and Glens",
                    "Central Bedfordshire" => "Central Bedfordshire",
                    "Ceredigion" => "Ceredigion",
                    "Cheshire East" => "Cheshire East",
                    "Cheshire West and Chester" => "Cheshire West and Chester",
                    "Clackmannanshire" => "Clackmannanshire",
                    "Conwy" => "Conwy",
                    "Cornwall" => "Cornwall",
                    "Coventry" => "Coventry",
                    "Croydon" => "Croydon",
                    "Cumbria" => "Cumbria",
                    "Darlington" => "Darlington",
                    "Denbighshire" => "Denbighshire",
                    "Derby" => "Derby",
                    "Derbyshire" => "Derbyshire",
                    "Derry and Strabane" => "Derry and Strabane",
                    "Devon" => "Devon",
                    "Doncaster" => "Doncaster",
                    "Dorset" => "Dorset",
                    "Dudley" => "Dudley",
                    "Dumfries and Galloway" => "Dumfries and Galloway",
                    "Dundee City" => "Dundee City",
                    "Durham, County" => "Durham, County",
                    "Ealing" => "Ealing",
                    "East Ayrshire" => "East Ayrshire",
                    "East Dunbartonshire" => "East Dunbartonshire",
                    "East Lothian" => "East Lothian",
                    "East Renfrewshire" => "East Renfrewshire",
                    "East Riding of Yorkshire" => "East Riding of Yorkshire",
                    "East Sussex" => "East Sussex",
                    "Edinburgh, City of" => "Edinburgh, City of",
                    "Eilean Siar" => "Eilean Siar",
                    "Enfield" => "Enfield",
                    "Essex" => "Essex",
                    "Falkirk" => "Falkirk",
                    "Fermanagh and Omagh" => "Fermanagh and Omagh",
                    "Fife" => "Fife",
                    "Flintshire" => "Flintshire",
                    "Gateshead" => "Gateshead",
                    "Glasgow City" => "Glasgow City",
                    "Gloucestershire" => "Gloucestershire",
                    "Greenwich" => "Greenwich",
                    "Gwynedd" => "Gwynedd",
                    "Hackney" => "Hackney",
                    "Halton" => "Halton",
                    "Hammersmith and Fulham" => "Hammersmith and Fulham",
                    "Hampshire" => "Hampshire",
                    "Haringey" => "Haringey",
                    "Harrow" => "Harrow",
                    "Hartlepool" => "Hartlepool",
                    "Havering" => "Havering",
                    "Herefordshire" => "Herefordshire",
                    "Hertfordshire" => "Hertfordshire",
                    "Highland" => "Highland",
                    "Hillingdon" => "Hillingdon",
                    "Hounslow" => "Hounslow",
                    "Inverclyde" => "Inverclyde",
                    "Isle of Anglesey" => "Isle of Anglesey",
                    "Isle of Wight" => "Isle of Wight",
                    "Isles of Scilly" => "Isles of Scilly",
                    "Islington" => "Islington",
                    "Kensington and Chelsea" => "Kensington and Chelsea",
                    "Kent" => "Kent",
                    "Kingston upon Hull" => "Kingston upon Hull",
                    "Kingston upon Thames" => "Kingston upon Thames",
                    "Kirklees" => "Kirklees",
                    "Knowsley" => "Knowsley",
                    "Lambeth" => "Lambeth",
                    "Lancashire" => "Lancashire",
                    "Leeds" => "Leeds",
                    "Leicester" => "Leicester",
                    "Leicestershire" => "Leicestershire",
                    "Lewisham" => "Lewisham",
                    "Lincolnshire" => "Lincolnshire",
                    "Lisburn and Castlereagh" => "Lisburn and Castlereagh",
                    "Liverpool" => "Liverpool",
                    "London, City of" => "London, City of",
                    "Luton" => "Luton",
                    "Manchester" => "Manchester",
                    "Medway" => "Medway",
                    "Merthyr Tydfil" => "Merthyr Tydfil",
                    "Merton" => "Merton",
                    "Mid and East Antrim" => "Mid and East Antrim",
                    "Mid-Ulster" => "Mid-Ulster",
                    "Middlesbrough" => "Middlesbrough",
                    "Midlothian" => "Midlothian",
                    "Milton Keynes" => "Milton Keynes",
                    "Monmouthshire" => "Monmouthshire",
                    "Moray" => "Moray",
                    "Neath Port Talbot" => "Neath Port Talbot",
                    "Newcastle upon Tyne" => "Newcastle upon Tyne",
                    "Newham" => "Newham",
                    "Newport" => "Newport",
                    "Newry, Mourne and Down" => "Newry, Mourne and Down",
                    "Norfolk" => "Norfolk",
                    "North Ayrshire" => "North Ayrshire",
                    "North East Lincolnshire" => "North East Lincolnshire",
                    "North Lanarkshire" => "North Lanarkshire",
                    "North Lincolnshire" => "North Lincolnshire",
                    "North Northamptonshire" => "North Northamptonshire",
                    "North Somerset" => "North Somerset",
                    "North Tyneside" => "North Tyneside",
                    "North Yorkshire" => "North Yorkshire",
                    "Northumberland" => "Northumberland",
                    "Nottingham" => "Nottingham",
                    "Nottinghamshire" => "Nottinghamshire",
                    "Oldham" => "Oldham",
                    "Orkney Islands" => "Orkney Islands",
                    "Oxfordshire" => "Oxfordshire",
                    "Pembrokeshire" => "Pembrokeshire",
                    "Perth and Kinross" => "Perth and Kinross",
                    "Peterborough" => "Peterborough",
                    "Plymouth" => "Plymouth",
                    "Portsmouth" => "Portsmouth",
                    "Powys" => "Powys",
                    "Reading" => "Reading",
                    "Redbridge" => "Redbridge",
                    "Redcar and Cleveland" => "Redcar and Cleveland",
                    "Renfrewshire" => "Renfrewshire",
                    "Rhondda Cynon Taff" => "Rhondda Cynon Taff",
                    "Richmond upon Thames" => "Richmond upon Thames",
                    "Rochdale" => "Rochdale",
                    "Rotherham" => "Rotherham",
                    "Rutland" => "Rutland",
                    "Salford" => "Salford",
                    "Sandwell" => "Sandwell",
                    "Scottish Borders" => "Scottish Borders",
                    "Sefton" => "Sefton",
                    "Sheffield" => "Sheffield",
                    "Shetland Islands" => "Shetland Islands",
                    "Shropshire" => "Shropshire",
                    "Slough" => "Slough",
                    "Solihull" => "Solihull",
                    "Somerset" => "Somerset",
                    "South Ayrshire" => "South Ayrshire",
                    "South Gloucestershire" => "South Gloucestershire",
                    "South Lanarkshire" => "South Lanarkshire",
                    "South Tyneside" => "South Tyneside",
                    "Southampton" => "Southampton",
                    "Southend-on-Sea" => "Southend-on-Sea",
                    "Southwark" => "Southwark",
                    "St. Helens" => "St. Helens",
                    "Staffordshire" => "Staffordshire",
                    "Stirling" => "Stirling",
                    "Stockport" => "Stockport",
                    "Stockton-on-Tees" => "Stockton-on-Tees",
                    "Stoke-on-Trent" => "Stoke-on-Trent",
                    "Suffolk" => "Suffolk",
                    "Sunderland" => "Sunderland",
                    "Surrey" => "Surrey",
                    "Sutton" => "Sutton",
                    "Swansea" => "Swansea",
                    "Swindon" => "Swindon",
                    "Tameside" => "Tameside",
                    "Telford and Wrekin" => "Telford and Wrekin",
                    "Thurrock" => "Thurrock",
                    "Torbay" => "Torbay",
                    "Torfaen" => "Torfaen",
                    "Tower Hamlets" => "Tower Hamlets",
                    "Trafford" => "Trafford",
                    "Vale of Glamorgan, The" => "Vale of Glamorgan, The",
                    "Wakefield" => "Wakefield",
                    "Walsall" => "Walsall",
                    "Waltham Forest" => "Waltham Forest",
                    "Wandsworth" => "Wandsworth",
                    "Warrington" => "Warrington",
                    "Warwickshire" => "Warwickshire",
                    "West Berkshire" => "West Berkshire",
                    "West Dunbartonshire" => "West Dunbartonshire",
                    "West Lothian" => "West Lothian",
                    "West Northamptonshire" => "West Northamptonshire",
                    "West Sussex" => "West Sussex",
                    "Westminster" => "Westminster",
                    "Wigan" => "Wigan",
                    "Wiltshire" => "Wiltshire",
                    "Windsor and Maidenhead" => "Windsor and Maidenhead",
                    "Wirral" => "Wirral",
                    "Wokingham" => "Wokingham",
                    "Wolverhampton" => "Wolverhampton",
                    "Worcestershire" => "Worcestershire",
                    "Wrexham" => "Wrexham",
                    "York" => "York",
                ],
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
