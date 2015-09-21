<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_TaxZoneModel
 *
 * @property int                   $id
 * @property string                $name
 * @property string                $description
 * @property bool                  $countryBased
 * @property bool                  $default
 *
 * @property Commerce_CountryModel[] $countries
 * @property Commerce_StateModel[]   $states
 * @package Craft
 */
class Commerce_TaxZoneModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/taxzones/'.$this->id);
	}

	/**
	 * @return array
	 */
	public function getCountriesIds ()
	{
		return array_map(function ($country)
		{
			return $country->id;
		}, $this->countries);
	}

	/**
	 * @return array
	 */
	public function getStatesIds ()
	{
		return array_map(function ($state)
		{
			return $state->id;
		}, $this->states);
	}

	/**
	 * @return array
	 */
	public function getCountriesNames ()
	{
		return array_map(function ($country)
		{
			return $country->name;
		}, $this->countries);
	}

	/**
	 * @return array
	 */
	public function getStatesNames ()
	{
		return array_map(function ($state)
		{
			return $state->formatName();
		}, $this->states);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'description'  => AttributeType::String,
			'countryBased' => [AttributeType::Bool, 'default' => 1],
			'default'      => [AttributeType::Bool, 'default' => 0],
		];
	}
}