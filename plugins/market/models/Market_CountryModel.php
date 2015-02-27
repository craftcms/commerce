<?php

namespace Craft;

/**
 * Class Market_CountryModel
 *
 * @property int    $id
 * @property string $name
 * @property string $iso
 * @property bool   $stateRequired
 * @package Craft
 */
class Market_CountryModel extends BaseModel
{
	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/countries/' . $this->id);
	}

	protected function defineAttributes()
	{
		return [
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'iso'           => AttributeType::String,
			'stateRequired' => AttributeType::Bool,
		];
	}

}