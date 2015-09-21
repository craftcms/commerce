<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_OrderHistoryModel
 *
 * @property int                     id
 * @property string                  message
 *
 * @property int                     orderId
 * @property int                     prevStatusId
 * @property int                     newStatusId
 * @property int                     customerId
 *
 * @property Commerce_OrderModel       order
 * @property Commerce_OrderStatusModel prevStatus
 * @property Commerce_OrderStatusModel newStatus
 * @property Commerce_CustomerModel    customer
 *
 * @package Craft
 */
class Commerce_OrderHistoryModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/orderhistories/'.$this->id);
	}

	protected function defineAttributes ()
	{
		return [
			'id'           => AttributeType::Number,
			'message'      => [AttributeType::String],
			'orderId'      => [AttributeType::Number],
			'prevStatusId' => [AttributeType::Number],
			'newStatusId'  => [AttributeType::Number],
			'customerId'   => [AttributeType::Number],
		];
	}
}