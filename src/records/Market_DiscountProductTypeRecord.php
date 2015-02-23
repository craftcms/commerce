<?php

namespace Craft;

/**
 * Class Market_DiscountProductTypeRecord
 *
 * @property int id
 * @property int discountId
 * @property int productTypeId
 * @package Craft
 */
class Market_DiscountProductTypeRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'market_discount_producttypes';
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
	{
		return [
			['columns' => ['discountId', 'productTypeId'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'discount'          => [static::BELONGS_TO, 'Market_DiscountRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
			'productType'   => [static::BELONGS_TO, 'Market_ProductTypeRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
		];
	}

	protected function defineAttributes()
	{
		return [
			'discountId'        => [AttributeType::Number, 'required' => true],
			'productTypeId' => [AttributeType::Number, 'required' => true],
		];
	}


}