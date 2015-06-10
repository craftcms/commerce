<?php

namespace Craft;

/**
 * Class Market_ProductTypeModel
 *
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property bool   $hasUrls
 * @property string $template
 * @property string $urlFormat
 * @property int    $productFieldLayoutId
 * @package Craft
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getProductFieldLayout()
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
			'productFieldLayout' => new FieldLayoutBehavior('Market_Product'),
		];
	}

	protected function defineAttributes()
	{
		return [
			'id'                   => AttributeType::Number,
			'name'                 => AttributeType::String,
			'handle'               => AttributeType::String,
			'hasUrls'              => AttributeType::Bool,
			'urlFormat'            => AttributeType::UrlFormat,
			'template'             => AttributeType::Template,
			'productFieldLayoutId' => AttributeType::Number,
		];
	}

}