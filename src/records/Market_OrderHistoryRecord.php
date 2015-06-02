<?php
namespace Craft;

/**
 * Class Market_OrderHistoryRecord
 *
 * @property int                          id
 * @property string                       message
 *
 * @property int                          orderId
 * @property int                          prevStatusId
 * @property int                          newStatusId
 * @property int                          customerId
 *
 * @property Market_OrderRecord           order
 * @property Market_OrderStatusRecord     prevStatus
 * @property Market_OrderStatusRecord     newStatus
 * @property Market_CustomerRecord        customer
 * @package Craft
 */
class Market_OrderHistoryRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_orderhistories';
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
            'order'      => [static::BELONGS_TO, 'Market_OrderRecord', 'required' => true, 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE],
            'prevStatus' => [static::BELONGS_TO, 'Market_OrderStatusRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE],
            'newStatus'  => [static::BELONGS_TO, 'Market_OrderStatusRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE],
			'customer'   => [static::BELONGS_TO, 'Market_CustomerRecord', 'required' => true, 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
        return [
			'orderId'    => [AttributeType::Number, 'required' => true],
			'customerId' => [AttributeType::Number, 'required' => true],
			'message'    => [AttributeType::Mixed],
		];
	}

}