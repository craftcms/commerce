<?php

namespace Craft;

/**
 * Class Market_OrderAdjustmentRecord
 *
 * @property int                $id
 * @property string             $name
 * @property string             description
 * @property string             $type
 * @property float              $amount
 * @property string             optionsJson
 * @property int                $orderId
 *
 * @property Market_OrderRecord $order
 * @package Craft
 */
class Market_OrderAdjustmentRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_orderadjustments';
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['orderId']],
		];
	}

	public function defineRelations()
	{
		return [
			'order' => [self::BELONGS_TO, 'Market_OrderRecord', 'required' => true, 'onDelete' => static::CASCADE ],
		];
	}

	protected function defineAttributes()
	{
		return [
			'type'        => [AttributeType::String, 'required' => true],
			'name'        => [AttributeType::String],
			'description' => [AttributeType::String],
			'amount'      => [AttributeType::Number, 'required' => true, 'decimals' => 5],
			'optionsJson' => [AttributeType::Mixed, 'required' => true],
			'orderId'     => [AttributeType::Number, 'required' => true],
		];
	}
}