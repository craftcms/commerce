<?php

namespace Craft;

/**
 * Class Market_OrderRecord
 * @package Craft
 *
 * @property int    id
 * @property string number
 * @property string couponCode
 * @property string state
 * @property float  itemTotal
 * @property float  finalPrice
 * @property float  baseDiscount
 * @property float  baseShippingRate
 * @property string $email
 * @property DateTime completedAt
 * @property string	$lastIp
 * @property int    typeId
 * @property int    billingAddressId
 * @property int    shippingAddressId
 * @property int    shippingMethodId
 *
 * @property Market_OrderTypeRecord type
 * @property Market_LineItemRecord[] lineItems
 * @property Market_AddressRecord billingAddress
 * @property Market_AddressRecord shippingAddress
 * @property Market_ShippingMethodRecord shippingMethod
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
			'discount'        => [static::HAS_ONE, 'Market_DiscountRecord', ['couponCode' => 'code']],
			'shippingMethod'  => [static::BELONGS_TO, 'Market_ShippingMethodRecord'],
		];
	}

	/**
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
			'couponCode'          => [AttributeType::String],
			'state'               => [AttributeType::Enum, 'required' => true, 'default' => 'cart', 'values' => self::$states],
            'itemTotal'           => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
            'baseDiscount'        => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
            'baseShippingRate'    => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
            'finalPrice'          => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
            'email'               => AttributeType::String,
            'completedAt'         => AttributeType::DateTime,
			'currency'            => AttributeType::String,
			'lastIp'              => AttributeType::String
			//TODO add 'shipmentState'
			//TODO add 'paymentState'
		];
	}

}