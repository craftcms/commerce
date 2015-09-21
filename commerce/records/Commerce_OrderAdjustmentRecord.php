<?php

namespace Craft;

/**
 * Class Commerce_OrderAdjustmentRecord
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
class Commerce_OrderAdjustmentRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_orderadjustments';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['orderId']],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'order' => [
				self::BELONGS_TO,
				'Commerce_OrderRecord',
				'required' => true,
				'onDelete' => static::CASCADE
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
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