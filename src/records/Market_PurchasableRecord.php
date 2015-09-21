<?php

namespace Craft;

/**
 * Class Market_PurchasableRecord
 *
 * @property int                  id
 * @property string               sku
 * @property float                price
 *
 * @property Market_VariantRecord $implicit
 * @package Craft
 */
class Market_PurchasableRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'market_purchasables';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['sku'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'element' => [
				static::BELONGS_TO,
				'ElementRecord',
				'id',
				'required' => true,
				'onDelete' => static::CASCADE
			]
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'sku'   => [AttributeType::String, 'required' => true],
			'price' => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true
			]
		];
	}

}