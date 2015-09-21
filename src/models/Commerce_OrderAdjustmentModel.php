<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_OrderAdjustmentModel
 *
 * @property int                $id
 * @property string             $name
 * @property string             description
 * @property string             $type
 * @property float              $amount
 * @property string             optionsJson
 * @property int                $orderId
 *
 * @property Commerce_OrderRecord $order
 * @package Craft
 */
class Commerce_OrderAdjustmentModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'          => AttributeType::Number,
			'type'        => [AttributeType::String, 'required' => true],
			'name'        => [AttributeType::String],
			'description' => [AttributeType::String],
			'amount'      => [
				AttributeType::Number,
				'required' => true,
				'decimals' => 5
			],
			'optionsJson' => [AttributeType::Mixed, 'required' => true],
			'orderId'     => [AttributeType::Number, 'required' => true],
		];
	}
}