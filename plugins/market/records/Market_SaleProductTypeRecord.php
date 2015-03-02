<?php

namespace Craft;

/**
 * Class Market_SaleProductTypeRecord
 *
 * @property int id
 * @property int saleId
 * @property int productTypeId
 * @package Craft
 */
class Market_SaleProductTypeRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'market_sale_producttypes';
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
	{
		return [
			['columns' => ['saleId', 'productTypeId'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'sale'        => [static::BELONGS_TO, 'Market_SaleRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
			'productType' => [static::BELONGS_TO, 'Market_ProductTypeRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
		];
	}

	protected function defineAttributes()
	{
		return [
			'saleId'        => [AttributeType::Number, 'required' => true],
			'productTypeId' => [AttributeType::Number, 'required' => true],
		];
	}

}