<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150124_124600_stripey_States extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		$states = array(
			'AU' => array(
				'ACT' => 'Australian Capital Territory',
				'NSW' => 'New South Wales',
				'NT'  => 'Northern Territory',
				'QLD' => 'Queensland',
				'SA'  => 'South Australia',
				'TAS' => 'Tasmania',
				'VIC' => 'Victoria',
				'WA'  => 'Western Australia',
			),
			'CA' => array(
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
			),
			'US' => array(
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
			),
		);

		$criteria = new \CDbCriteria();
		$criteria->addInCondition('iso', array_keys($states));
		$countries = Stripey_CountryRecord::model()->findAll($criteria);
		$code2id   = array();
		foreach ($countries as $record) {
			$code2id[$record->iso] = $record->id;
		}

		$rows = array();
		foreach ($states as $iso => $list) {
			foreach ($list as $abbr => $name) {
				$rows[] = array($code2id[$iso], $abbr, $name);
			}
		}

		$table = Stripey_StateRecord::model()->getTableName();
		craft()->db->createCommand()->insertAll($table, array('countryId', 'abbreviation', 'name'), $rows);

		return true;
	}
}
