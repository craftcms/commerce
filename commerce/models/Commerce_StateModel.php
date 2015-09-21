<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_StateModel
 *
 * @property int                  $id
 * @property string               $name
 * @property string               $abbreviation
 * @property int                  $countryId
 *
 * @property Commerce_CountryRecord $country
 * @package Craft
 */
class Commerce_StateModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/states/'.$this->id);
	}

	/**
	 * @return string
	 */
	function __toString ()
	{
		return (string)$this->name;
	}

	/**
	 * @return string
	 */
	public function formatName ()
	{
		return $this->name.' ('.$this->country->name.')';
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'abbreviation' => AttributeType::String,
			'countryId'    => AttributeType::Number,
		];
	}
}