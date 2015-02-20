<?php

namespace Craft;

/**
 * Class Market_OrderRecord
 * @package Craft
 *
 * @property int    $id
 * @property string $number
 * @property string $state
 * @property float  $itemTotal
 * @property float  $adjustmentTotal
 * @property string $email
 * @property DateTime completedAt
 * @property string	$lastIp
 * @property int    typeId
 * @property int    billingAddressId
 * @property int    shippingAddressId
 *
 * @property Market_OrderTypeRecord type
 * @property Market_LineItemRecord[] lineItems
 * @property Market_AddressRecord billingAddress
 * @property Market_AddressRecord shippingAddress
 */
class Market_OrderRecord extends BaseRecord
{
	const STATE_CART = 'cart';
	const STATE_ADDRESS = 'address';
	const STATE_PAYMENT = 'payment';
	const STATE_CONFIRM = 'confirm';
	const STATE_COMPLETE = 'complete';

	public static $states = [
		self::STATE_CART,
		self::STATE_ADDRESS,
		/*'delivery',*/
		self::STATE_PAYMENT,
		self::STATE_CONFIRM,
		self::STATE_COMPLETE
	];

	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_orders';
	}

	public function defineRelations()
	{
		return [
			'type'            => [static::BELONGS_TO, 'Market_OrderTypeRecord', 'required' => true, 'onDelete' => static::CASCADE],
			'lineItems'       => [static::HAS_MANY, 'Market_LineItemRecord', 'orderId'],
			'billingAddress'  => [static::BELONGS_TO, 'Market_AddressRecord'],
			'shippingAddress' => [static::BELONGS_TO, 'Market_AddressRecord'],
		];
	}

	/**
	 * @inheritDoc BaseRecord::defineIndexes()
	 *
	 * @return array
	 */
	public function defineIndexes()
	{
		return [
			['columns' => ['typeId']],
			['columns' => ['number']],
		];
	}

	protected function defineAttributes()
	{
		return [
			'number'              => [AttributeType::String, 'length' => 32],
			'state'               => [AttributeType::Enum, 'required' => true, 'default' => 'cart', 'values' => self::$states],
			'itemTotal'           => [AttributeType::Number, 'decimals' => 4],
			'adjustmentTotal'     => [AttributeType::Number, 'decimals' => 4],
			'email'               => AttributeType::String,
			'completedAt'         => AttributeType::DateTime,
			'currency'            => AttributeType::String,
			'lastIp'              => AttributeType::String
			//TODO add 'shipmentState'
			//TODO add 'paymentState'
		];
	}

}