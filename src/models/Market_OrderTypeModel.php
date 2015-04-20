<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderTypeModel
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $handle
 * @property int                         $fieldLayoutId
 * @property int                         shippingMethodId
 *
 * @property FieldLayoutRecord           fieldLayout
 * @property Market_ShippingMethodRecord shippingMethod
 * @property Market_OrderStatusModel[]   orderStatuses
 * @property Market_OrderStatusModel     defaultStatus
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 * @package Craft
 */
class Market_OrderTypeModel extends BaseModel
{
	use Market_ModelRelationsTrait;

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
		return [
			'fieldLayout' => new FieldLayoutBehavior('Market_Order'),
		];
	}

	protected function defineAttributes()
	{
		return [
			'id'               => AttributeType::Number,
			'name'             => AttributeType::String,
			'handle'           => AttributeType::String,
			'fieldLayoutId'    => AttributeType::Number,
			'shippingMethodId' => AttributeType::Number,
		];
	}

}