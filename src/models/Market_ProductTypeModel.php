<?php

namespace Craft;

/**
 * Class Market_ProductTypeModel
 *
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property int    $fieldLayoutId
 * @package Craft
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
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
		return [
			'fieldLayout' => new FieldLayoutBehavior('Market_Product'),
		];
	}

	protected function defineAttributes()
	{
		return [
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'handle'        => AttributeType::String,
			'type'        => AttributeType::String,
			'fieldLayoutId' => AttributeType::Number
		];
	}

}