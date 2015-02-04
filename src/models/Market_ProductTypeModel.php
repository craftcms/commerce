<?php

namespace Craft;

/**
 * Class Market_ProductTypeModel
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property int    $fieldLayoutId
 * @package Craft
 */
class Market_ProductTypeModel extends BaseModel
{
	function __toString()
	{
		return Craft::t($this->handle);
	}

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/producttypes/' . $this->id);
	}

	public function behaviors()
	{
		return array(
			'fieldLayout' => new FieldLayoutBehavior('Market_Product'),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'handle'        => AttributeType::String,
			'fieldLayoutId' => AttributeType::Number
		);
	}

}