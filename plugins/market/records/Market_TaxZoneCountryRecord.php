<?php
namespace Craft;

class Market_TaxZoneCountryRecord extends BaseRecord
{

	public function getTableName()
	{
		return "market_taxzone_countries";
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['taxZoneId']],
			['columns' => ['countryId']],
			['columns' => ['taxZoneId', 'countryId'], 'unique' => true],
		];
	}


	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'taxZone' => [static::BELONGS_TO, 'Market_TaxZoneRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
			'country' => [static::BELONGS_TO, 'Market_CountryRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
		];
	}

}