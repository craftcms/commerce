<?php

namespace Craft;

/**
 * Class Commerce_TaxCategoryModel
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool   $default
 * @package Craft
 */
class Commerce_TaxCategoryModel extends BaseModel
{
	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/taxcategories/'.$this->id);
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