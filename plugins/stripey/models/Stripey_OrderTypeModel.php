<?php

namespace Craft;

/**
 * Class Stripey_OrderTypeModel
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property int    $fieldLayoutId
 * @package Craft
 */
class Stripey_OrderTypeModel extends BaseModel
{
	function __toString()
	{
		return Craft::t($this->handle);
	}

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('stripey/settings/ordertypes/' . $this->id);
	}

	public function behaviors()
	{
		return array(
			'fieldLayout' => new FieldLayoutBehavior('Stripey_Order'),
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