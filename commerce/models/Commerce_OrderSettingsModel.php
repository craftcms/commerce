<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_OrderSettingsModel
 *
 * @property int               $id
 * @property string            $name
 * @property string            $handle
 * @property int               $fieldLayoutId
 *
 * @property FieldLayoutRecord fieldLayout
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 * @package Craft
 */
class Commerce_OrderSettingsModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return null|string
	 */
	function __toString ()
	{
		return Craft::t($this->handle);
	}

	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/ordersettings');
	}

	/**
	 * @return array
	 */
	public function behaviors ()
	{
		return [
			'fieldLayout' => new FieldLayoutBehavior('Commerce_Order'),
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'handle'        => AttributeType::String,
			'fieldLayoutId' => AttributeType::Number
		];
	}

}