<?php

namespace Market\Seed;
use Craft\Market_CountryRecord;
use Craft\Market_StateRecord;

/**
 * Class Market_StatesSeeder
 * @package Market\Seed
 */
class Market_StatesSeeder implements Market_SeederInterface {

    public function seed()
    {
        $states = [
            'AU' => [
                'ACT' => 'Australian Capital Territory',
                'NSW' => 'New South Wales',
                'NT'  => 'Northern Territory',
                'QLD' => 'Queensland',
                'SA'  => 'South Australia',
                'TAS' => 'Tasmania',
                'VIC' => 'Victoria',
                'WA'  => 'Western Australia',
            ],
            'CA' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NT' => 'Northwest Territories',
                'NS' => 'Nova Scotia',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ],
            'US' => [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'DC' => 'District of Columbia',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
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
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
            ],
        ];

        $criteria = new \CDbCriteria();
        $criteria->addInCondition('iso', array_keys($states));
        $countries = Market_CountryRecord::model()->findAll($criteria);
        $code2id = [];
        foreach ($countries as $record) {
            $code2id[$record->iso] = $record->id;
        }

        $rows = [];
        foreach ($states as $iso => $list) {
            foreach ($list as $abbr => $name) {
                $rows[] = [$code2id[$iso], $abbr, $name];
            }
        }

        $table = Market_StateRecord::model()->getTableName();
        \Craft\craft()->db->createCommand()->insertAll($table, ['countryId', 'abbreviation', 'name'], $rows);
    }
}