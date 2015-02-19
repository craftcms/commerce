<?php

namespace Craft;

/**
 * Class Market_VariantRecord
 *
 * @property int      id
 * @property int      productId
 * @property bool     isMaster
 * @property string   sku
 * @property float    price
 * @property float    width
 * @property float    height
 * @property float    length
 * @property float    weight
 * @property int	  stock
 * @property bool 	  unlimitedStock
 * @property int	  minQty
 * @property DateTime deletedAt
 *
 * @property Market_ProductRecord $product
 * @package Craft
 */
class Market_VariantRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_variants';
	}

	public function defaultScope()
	{
		return [
			'condition' => 'deletedAt IS NULL',
		];
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['sku'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'product' => [self::BELONGS_TO, 'Market_ProductRecord', 'onDelete' => self::SET_NULL, 'onUpdate' => self::CASCADE],
		];
	}

	protected function defineAttributes()
	{
		return [
			'isMaster'  => [AttributeType::Bool, 'default' => 0, 'required' => true],
			'sku'       => [AttributeType::String, 'required' => true],
			'price'     => [AttributeType::Number, 'decimals' => 4, 'required' => true],
			'width'     => [AttributeType::Number, 'decimals' => 4],
			'height'    => [AttributeType::Number, 'decimals' => 4],
			'length'    => [AttributeType::Number, 'decimals' => 4],
			'weight'    => [AttributeType::Number, 'decimals' => 4],
			'stock'     => [AttributeType::Number, 'unsigned' => true, 'required' => true, 'default' => 0],
			'unlimitedStock' => [AttributeType::Bool, 'default' => 0, 'required' => true],
			'minQty'    => [AttributeType::Number, 'unsigned' => true],
			'deletedAt' => [AttributeType::DateTime],
		];
	}

}