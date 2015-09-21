<?php

namespace Craft;

/**
 * Class Commerce_SaleProductTypeRecord
 *
 * @property int id
 * @property int saleId
 * @property int productTypeId
 * @package Craft
 */
class Commerce_SaleProductTypeRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_sale_producttypes';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['saleId', 'productTypeId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'sale'        => [
				static::BELONGS_TO,
				'Commerce_SaleRecord',
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
			'saleId'        => [AttributeType::Number, 'required' => true],
			'productTypeId' => [AttributeType::Number, 'required' => true],
		];
	}

}