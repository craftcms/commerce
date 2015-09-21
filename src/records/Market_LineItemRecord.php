<?php

namespace Craft;

/**
 * Class Market_LineItemRecord
 *
 * @package Craft
 *
 * @property int                      id
 * @property float                    price
 * @property float                    saleAmount
 * @property float                    salePrice
 * @property float                    tax
 * @property float                    shippingCost
 * @property float                    discount
 * @property float                    weight
 * @property float                    height
 * @property float                    width
 * @property float                    length
 * @property float                    total
 * @property int                      qty
 * @property string                   note
 * @property string                   snapshot
 *
 * @property int                      orderId
 * @property int                      purchasableId
 * @property int                      taxCategoryId
 *
 * @property Market_OrderRecord       order
 * @property Market_VariantRecord     variant
 * @property Market_TaxCategoryRecord taxCategory
 */
class Market_LineItemRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return "market_lineitems";
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['orderId', 'purchasableId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'order'       => [
				static::BELONGS_TO,
				'Market_OrderRecord',
				'required' => true,
				'onDelete' => static::CASCADE
			],
			'purchasable' => [
				static::BELONGS_TO,
				'ElementRecord',
				'onUpdate' => self::CASCADE,
				'onDelete' => self::SET_NULL
			],
			'taxCategory' => [
				static::BELONGS_TO,
				'Market_TaxCategoryRecord',
				'onUpdate' => self::CASCADE,
				'onDelete' => self::RESTRICT,
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
			'price'         => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true
			],
			'saleAmount'    => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'salePrice'     => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'tax'           => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'shippingCost'  => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'discount'      => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'weight'        => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'height'        => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'length'        => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'width'         => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'total'         => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'qty'           => [
				AttributeType::Number,
				'min'      => 0,
				'required' => true
			],
			'note'          => AttributeType::Mixed,
			'snapshot'      => [AttributeType::Mixed, 'required' => true],
			'taxCategoryId' => [AttributeType::Number, 'required' => true],
		];
	}
}