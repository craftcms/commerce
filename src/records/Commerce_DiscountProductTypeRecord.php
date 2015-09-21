<?php

namespace Craft;

/**
 * Class Commerce_DiscountProductTypeRecord
 *
 * @property int id
 * @property int discountId
 * @property int productTypeId
 * @package Craft
 */
class Commerce_DiscountProductTypeRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_discount_producttypes';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['discountId', 'productTypeId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'discount'    => [
				static::BELONGS_TO,
				'Commerce_DiscountRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
			'productType' => [
				static::BELONGS_TO,
				'Commerce_ProductTypeRecord',
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
			'discountId'    => [AttributeType::Number, 'required' => true],
			'productTypeId' => [AttributeType::Number, 'required' => true],
		];
	}

}