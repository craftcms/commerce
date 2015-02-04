<?php

namespace Craft;

/**
 * Class Stripey_CountryModel
 *
 * @property int    $id
 * @property string $name
 * @property string $iso
 * @property bool   $stateRequired
 * @package Craft
 */
class Stripey_CountryModel extends BaseModel
{
	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('stripey/settings/countries/' . $this->id);
	}

	protected function defineAttributes()
	{
		return array(
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'iso'           => AttributeType::String,
			'stateRequired' => AttributeType::Bool,
		);
	}

}