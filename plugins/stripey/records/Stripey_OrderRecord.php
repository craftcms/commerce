<?php

namespace Craft;

class Stripey_OrderRecord extends BaseRecord
{

	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return "stripey_orders";
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
			'specialInstructions' => AttributeType::String,
			'currency'            => AttributeType::String,
			'lastIp'              => AttributeType::String,
			'lastIp'              => AttributeType::Mixed,
			//TODO add 'shipmentState'
			//TODO add 'paymentState'
		);
	}

	public function defineRelations()
	{
		return array(
			'type'            => array(static::BELONGS_TO, 'Stripey_OrderTypeRecord', 'onDelete' => static::CASCADE),
//            'lineItems' => array(static::HAS_MANY, 'Stripey_OrderRecord'),
			'billingAddress'  => array(static::BELONGS_TO, 'Stripey_AddressRecord'),
			'shippingAddress' => array(static::BELONGS_TO, 'Stripey_AddressRecord'),
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

}