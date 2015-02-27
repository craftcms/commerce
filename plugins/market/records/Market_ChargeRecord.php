<?php
namespace Craft;

class Market_ChargeRecord extends BaseRecord
{

	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return "market_charges";
	}

	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
//            'customer' => array(static::BELONGS_TO, 'Market_CustomerRecord'),
			'element' => array(static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE),
		);
	}

	/**
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array(
			'stripeId' => AttributeType::String,
			'amount'   => AttributeType::Number,
		);
	}

}