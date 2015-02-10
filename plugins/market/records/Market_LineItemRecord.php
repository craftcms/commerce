<?php

namespace Craft;

class Market_LineItemRecord extends BaseRecord
{
	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return "market_lineitems";
	}

	protected function defineAttributes()
	{
		return array(
			'id'        => AttributeType::Number,
			'orderId'   => AttributeType::Number,
			'variantId' => AttributeType::Number,
			'price'     => array(AttributeType::Number, 'decimals' => 4),
			'quantity'  => AttributeType::Number
		);
	}

	public function defineRelations()
	{
		return array(
			'order' => array(static::BELONGS_TO, 'Market_OrderRecord', 'onDelete' => static::CASCADE),
		);
	}
}