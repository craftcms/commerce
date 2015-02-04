<?php

namespace Craft;

class Stripey_LineItemRecord extends BaseRecord
{
	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return "stripey_line_items";
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
}