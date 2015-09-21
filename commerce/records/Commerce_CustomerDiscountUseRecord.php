<?php

namespace Craft;

/**
 * Class Commerce_CustomerDiscountUseRecord
 *
 * @property int                   id
 * @property int                   discountId
 * @property int                   customerId
 * @property Commerce_DiscountRecord discount
 * @property Commerce_CustomerRecord customer
 * @package Craft
 */
class Commerce_CustomerDiscountUseRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_customer_discountuses';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['customerId', 'discountId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'discount' => [
				static::BELONGS_TO,
				'Commerce_DiscountRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
			'customer' => [
				static::BELONGS_TO,
				'Commerce_CustomerRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'discountId' => [AttributeType::Number, 'required' => true],
			'customerId' => [AttributeType::Number, 'required' => true],
			'uses'       => [
				AttributeType::Number,
				'required' => true,
				'min'      => 1
			],
		];
	}
}