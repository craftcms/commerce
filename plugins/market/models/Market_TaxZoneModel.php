<?php

namespace Craft;

/**
 * Class Market_TaxZoneModel
 *
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property bool   $countryBased
 * @package Craft
 */
class Market_TaxZoneModel extends BaseModel
{
	/** @var Market_CountryModel[] */
	private $countries = [];
	/** @var Market_StateModel[] */
	private $states = [];

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

	public function getCountriesNames()
	{
		return array_map(function ($country) {
			return $country->name;
		}, $this->countries);
	}

	public function getStatesNames()
	{
		return array_map(function ($state) {
			return $state->formatName();
		}, $this->states);
	}

	protected function defineAttributes()
	{
		return array(
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'description'  => AttributeType::String,
			'countryBased' => array(AttributeType::Bool, 'default' => 1),
		);
	}

	public static function populateModel($values)
	{
		$model = parent::populateModel($values);
		if (is_object($values) && $values instanceof Market_TaxZoneRecord) {

			$model->countries = Market_CountryModel::populateModels($values->countries);
			$model->states    = Market_StateModel::populateModels($values->states);
		}

		return $model;
	}
}