<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_LineItemModel
 *
 * @package Craft
 *
 * @property int                     id
 * @property float                   price
 * @property float                   saleAmount
 * @property float                   taxAmount
 * @property float                   shippingAmount
 * @property float                   discountAmount
 * @property float                   weight
 * @property float                   total
 * @property int                     qty
 * @property string                  optionsJson
 *
 * @property int                     orderId
 * @property int                     variantId
 * @property int                     taxCategoryId
 *
 * @property bool                    underSale
 *
 * @property Market_OrderModel       order
 * @property Market_VariantModel     variant
 * @property Market_TaxCategoryModel taxCategory
 */
class Market_LineItemModel extends BaseModel
{
	use Market_ModelRelationsTrait;

	/**
	 * @return bool
	 */
	public function getUnderSale()
	{
		return $this->saleAmount != 0;
	}

	public function getSubtotalWithSale()
	{
		return $this->qty * ($this->price + $this->saleAmount);
	}

	public function getPriceWithoutShipping()
	{
		return $this->price + $this->discountAmount + $this->saleAmount;
	}

	protected function defineAttributes()
	{
		return [
			'id'             => AttributeType::Number,
			'price'          => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'saleAmount'     => [AttributeType::Number, 'decimals' => 4, 'required' => true, 'default' => 0],
			'taxAmount'      => [AttributeType::Number, 'decimals' => 4, 'required' => true, 'default' => 0],
			'shippingAmount' => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'discountAmount' => [AttributeType::Number, 'decimals' => 4, 'required' => true, 'default' => 0],
			'weight'         => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'total'          => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'qty'            => [AttributeType::Number, 'min' => 0, 'required' => true],
			'optionsJson'    => [AttributeType::Mixed, 'required' => true],

			'variantId'      => AttributeType::Number,
			'orderId'        => AttributeType::Number,
			'taxCategoryId'  => [AttributeType::Number, 'required' => true],
		];
	}
}