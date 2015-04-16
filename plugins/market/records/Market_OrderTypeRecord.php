<?php
namespace Craft;

/**
 * Class Market_OrderTypeRecord
 *
 * @property int                         id
 * @property string                      name
 * @property string                      handle
 * @property int                         fieldLayoutId
 * @property int                         shippingMethodId
 *
 * @property FieldLayoutRecord           fieldLayout
 * @property Market_ShippingMethodRecord shippingMethod
 * @property Market_OrderStatusRecord[]  orderStatuses
 * @package Craft
 */
class Market_OrderTypeRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_ordertypes';
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
	{
		return [
			['columns' => ['handle'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'fieldLayout'    => [static::BELONGS_TO, 'FieldLayoutRecord', 'onDelete' => static::SET_NULL],
			'shippingMethod' => [static::BELONGS_TO, 'Market_ShippingMethodRecord', 'required' => true],
			'orderStatuses'  => [static::HAS_MANY, 'Market_OrderStatusRecord', 'orderTypeId'],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'name'             => [AttributeType::Name, 'required' => true],
			'handle'           => [AttributeType::Handle, 'required' => true],
			'shippingMethodId' => [AttributeType::Number, 'required' => true],
		];
	}

}