<?php

namespace Craft;

class Market_OrderRecord extends BaseRecord
{

	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return "market_orders";
	}

	public function defineRelations()
	{
		return array(
			'type'            => array(static::BELONGS_TO, 'Market_OrderTypeRecord', 'onDelete' => static::CASCADE),
			'lineItems'       => array(static::HAS_MANY, 'Market_OrderRecord','lineItemId'),
			'billingAddress'  => array(static::BELONGS_TO, 'Market_AddressRecord'),
			'shippingAddress' => array(static::BELONGS_TO, 'Market_AddressRecord'),
		);
	}

	/**
	 * @inheritDoc BaseRecord::defineIndexes()
	 *
	 * @return array
	 */
	public function defineIndexes()
	{
		return array(
			array('columns' => array('typeId'))
		);
	}

	protected function defineAttributes()
	{
		return array(
			'number'              => AttributeType::String,
			'state'               => array(AttributeType::Enum, 'required' => true, 'values' => array('cart', 'address', 'delivery', 'payment', 'confirm', 'complete'), 'default' => 'cart'),
			'itemTotal'           => array(AttributeType::Number, 'decimals' => 4),
			'adjustmentTotal'     => array(AttributeType::Number, 'decimals' => 4),
			'email'               => AttributeType::String,
			'userId'              => AttributeType::Number,
			'completedAt'         => AttributeType::DateTime,
			'currency'            => AttributeType::String,
			'lastIp'              => AttributeType::String
			//TODO add 'shipmentState'
			//TODO add 'paymentState'
		);
	}

}