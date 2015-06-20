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
 * @property bool   $hasVariants
 * @property string $template
 * @property string $urlFormat
 * @property int    $fieldLayoutId
 * @property int    $variantFieldLayoutId
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

	public function getCpEditVariantUrl()
	{
		return UrlHelper::getCpUrl('market/settings/producttypes/' . $this->id . '/variant');
	}

	public function behaviors()
	{
		return [
			'productFieldLayout' => new FieldLayoutBehavior('Market_Product', 'fieldLayoutId'),
			'variantFieldLayout' => new FieldLayoutBehavior('Market_Variant', 'variantFieldLayoutId'),
		];
	}

	protected function defineAttributes()
	{
		return [

			'id'                   => AttributeType::Number,
			'name'                 => AttributeType::String,
			'handle'               => AttributeType::String,
			'hasUrls'              => AttributeType::Bool,
			'hasVariants'          => AttributeType::Bool,
			'urlFormat'            => AttributeType::UrlFormat,
			'template'             => AttributeType::Template,
			'fieldLayoutId'        => AttributeType::Number,
			'variantFieldLayoutId' => AttributeType::Number,
		];
	}

}