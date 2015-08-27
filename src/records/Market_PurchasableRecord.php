<?php

namespace Craft;

/**
 * Class Market_PurchasableRecord
 *
 * @property int                  id
 * @property string               sku
 * @property float                price
 *
 * @property Market_VariantRecord $master
 * @package Craft
 */
class Market_PurchasableRecord extends BaseRecord
{

	public function getTableName ()
	{
		return 'market_purchasables';
	}

	public function defineIndexes ()
	{
		return [
			['columns' => ['sku'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'element'     => [
				static::BELONGS_TO,
				'ElementRecord',
				'id',
				'required' => true,
				'onDelete' => static::CASCADE
			]
		];
	}

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