<?php

namespace Craft;

/**
 * Class Market_SaleProductRecord
 *
 * @property int id
 * @property int saleId
 * @property int productId
 * @package Craft
 */
class Market_SaleProductRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'market_sale_products';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['saleId', 'productId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'sale'    => [
				static::BELONGS_TO,
				'Market_SaleRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
			'product' => [
				static::BELONGS_TO,
				'Market_ProductRecord',
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
			'saleId'    => [AttributeType::Number, 'required' => true],
			'productId' => [AttributeType::Number, 'required' => true],
		];
	}

}