<?php

namespace Craft;

/**
 * Class Market_TaxZoneRecord
 *
 * @property int                     $id
 * @property string                  $name
 * @property string                  $description
 * @property bool                    $countryBased
 * @property bool                    $default
 *
 * @property Market_CountryRecord[]  $countries
 * @property Market_StateRecord[]    $states
 * @package Craft
 */
class Market_TaxZoneRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_taxzones';
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['name'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'countries' => [static::MANY_MANY, 'Market_CountryRecord', 'market_taxzone_countries(countryId, taxZoneId)'],
			'states'    => [static::MANY_MANY, 'Market_StateRecord', 'market_taxzone_states(stateId, taxZoneId)'],
		];
	}

	protected function defineAttributes()
	{
		return [
			'name'         => [AttributeType::String, 'required' => true],
			'description'  => AttributeType::String,
			'countryBased' => [AttributeType::Bool, 'required' => true, 'default' => 1],
			'default'      => [AttributeType::Bool, 'default' => 0, 'required' => true],
		];
	}
}