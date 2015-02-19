<?php

namespace Craft;

/**
 * Class Market_OrderTypeModel
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property int    $fieldLayoutId
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 * @package Craft
 */
class Market_OrderTypeModel extends BaseModel
{
	function __toString()
	{
		return Craft::t($this->handle);
	}

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/ordertypes/' . $this->id);
	}

	public function behaviors()
	{
		return array(
			'fieldLayout' => new FieldLayoutBehavior('Market_Order'),
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