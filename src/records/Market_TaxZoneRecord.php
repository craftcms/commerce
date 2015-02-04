<?php

namespace Craft;

/**
 * Class Market_TaxZoneRecord
 *
 * @property int                     $id
 * @property string                  $name
 * @property string                  $description
 * @property bool                    $countryBased
 *
 * @property Market_CountryRecord[] $countries
 * @property Market_StateRecord[]   $states
 * @package Craft
 */
class Market_TaxZoneRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_taxzones';
	}

	public function defineRelations()
	{
		return array(
			'countries' => array(static::MANY_MANY, 'Market_CountryRecord', 'market_taxzone_countries(countryId, taxZoneId)'),
			'states'    => array(static::MANY_MANY, 'Market_StateRecord', 'market_taxzone_states(stateId, taxZoneId)'),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'name'         => array(AttributeType::String, 'required' => true),
			'description'  => AttributeType::String,
			'countryBased' => array(AttributeType::Bool, 'required' => true, 'default' => 1),
		);
	}
}