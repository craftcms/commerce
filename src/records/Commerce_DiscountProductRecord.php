<?php

namespace Craft;

/**
 * Class Commerce_DiscountProductRecord
 *
 * @property int id
 * @property int discountId
 * @property int productId
 * @package Craft
 */
class Commerce_DiscountProductRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_discount_products';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['discountId', 'productId'], 'unique' => true],
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
			'product'  => [
				static::BELONGS_TO,
				'Commerce_ProductRecord',
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
			'productId'  => [AttributeType::Number, 'required' => true],
		];
	}

}