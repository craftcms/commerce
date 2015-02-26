<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_TaxZoneModel
 *
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property bool   $countryBased
 * @property bool   $default
 *
 * @property Market_CountryModel[] $countries
 * @property Market_StateModel[] $states
 * @package Craft
 */
class Market_TaxZoneModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/taxzones/' . $this->id);
	}

	/**
	 * @return array
	 */
	public function getCountriesIds()
	{
		return array_map(function ($country) {
			return $country->id;
		}, $this->countries);
	}

	/**
	 * @return array
	 */
	public function getStatesIds()
	{
		return array_map(function ($state) {
			return $state->id;
		}, $this->states);
	}

    /**
     * @return array
     */
	public function getCountriesNames()
	{
		return array_map(function ($country) {
			return $country->name;
		}, $this->countries);
	}

    /**
     * @return array
     */
	public function getStatesNames()
	{
		return array_map(function ($state) {
			return $state->formatName();
		}, $this->states);
	}

	protected function defineAttributes()
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