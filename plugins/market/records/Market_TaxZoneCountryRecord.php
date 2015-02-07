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
		return array(
			array('columns' => array('taxZoneId')),
			array('columns' => array('countryId')),
			array('columns' => array('taxZoneId', 'countryId'), 'unique' => true),
		);
	}


	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'taxZone' => array(static::BELONGS_TO, 'Market_TaxZoneRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
			'country' => array(static::BELONGS_TO, 'Market_CountryRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
		);
	}

}