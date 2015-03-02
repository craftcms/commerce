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
		return [
//            'customer' => array(static::BELONGS_TO, 'Market_CustomerRecord'),
			'element' => [static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE],
		];
	}

	/**
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'stripeId' => AttributeType::String,
			'amount'   => AttributeType::Number,
		];
	}

}