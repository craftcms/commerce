<?php

namespace Craft;

/**
 * Class Stripey_ProductTypeModel
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property int    $fieldLayoutId
 * @package Craft
 */
class Stripey_ProductTypeModel extends BaseModel
{
	function __toString()
	{
		return Craft::t($this->handle);
	}

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('stripey/settings/producttypes/' . $this->id);
	}

	public function behaviors()
	{
		return array(
			'fieldLayout' => new FieldLayoutBehavior('Stripey_Product'),
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