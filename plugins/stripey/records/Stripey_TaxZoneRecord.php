<?php

namespace Craft;

/**
 * Class Stripey_TaxZoneRecord
 *
 * @property int                     $id
 * @property string                  $name
 * @property string                  $description
 * @property bool                    $countryBased
 *
 * @property Stripey_CountryRecord[] $countries
 * @property Stripey_StateRecord[]   $states
 * @package Craft
 */
class Stripey_TaxZoneRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'stripey_taxzones';
	}

	public function defineRelations()
	{
		return array(
			'countries' => array(static::MANY_MANY, 'Stripey_CountryRecord', 'stripey_taxzone_countries(countryId, taxZoneId)'),
			'states'    => array(static::MANY_MANY, 'Stripey_StateRecord', 'stripey_taxzone_states(stateId, taxZoneId)'),
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