<?php

namespace Craft;

/**
 * Class Market_TaxCategoryModel
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool   $default
 * @package Craft
 */
class Market_TaxCategoryModel extends BaseModel
{
	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('market/settings/taxcategories/'.$this->id);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'          => AttributeType::Number,
			'name'        => AttributeType::String,
			'handle'      => AttributeType::String,
			'description' => AttributeType::String,
			'default'     => AttributeType::Bool,
		];
	}

}