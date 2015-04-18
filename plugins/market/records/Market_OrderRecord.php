<?php

namespace Craft;

/**
 * Class Market_OrderRecord
 *
 * @package Craft
 *
 * @property int                         id
 * @property string                      number
 * @property string                      couponCode
 * @property float                       itemTotal
 * @property float                       finalPrice
 * @property float                       baseDiscount
 * @property float                       baseShippingRate
 * @property string                      email
 * @property DateTime                    completedAt
 * @property string                      lastIp
 * @property string                      returnUrl
 * @property string                      cancelUrl
 *
 * @property int                         typeId
 * @property int                         billingAddressId
 * @property int                         shippingAddressId
 * @property int                         shippingMethodId
 * @property int                         paymentMethodId
 * @property int                         customerId
 * @property int                         statusId
 *
 * @property Market_OrderTypeRecord      type
 * @property Market_LineItemRecord[]     lineItems
 * @property Market_AddressRecord        billingAddress
 * @property Market_AddressRecord        shippingAddress
 * @property Market_ShippingMethodRecord shippingMethod
 * @property Market_PaymentMethodRecord  paymentMethod
 * @property Market_TransactionRecord[]  transactions
 * @property Market_OrderStatusRecord[]  status
 * @property Market_OrderHistoryRecord[] histories
 */
class Market_OrderRecord extends BaseRecord
{
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
			'paymentMethod'   => [static::BELONGS_TO, 'Market_PaymentMethodRecord'],
			'customer'        => [static::BELONGS_TO, 'Market_CustomerRecord'],
			'transactions'    => [static::HAS_MANY, 'Market_TransactionRecord', 'orderId'],
			'element'         => [static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE],
			'status'          => [static::BELONGS_TO, 'Market_OrderStatusRecord', 'onDelete' => static::RESTRICT, 'onUpdate' => self::CASCADE],
			'histories'       => [static::HAS_MANY, 'Market_OrderHistoryRecord', 'orderId'],
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
			'number'           => [AttributeType::String, 'length' => 32],
			'couponCode'       => AttributeType::String,
			'itemTotal'        => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'baseDiscount'     => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'baseShippingRate' => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'finalPrice'       => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'email'            => AttributeType::String,
			'completedAt'      => AttributeType::DateTime,
			'currency'         => AttributeType::String,
			'lastIp'           => AttributeType::String,
			'returnUrl'        => AttributeType::String,
			'cancelUrl'        => AttributeType::String,
		];
	}

}
