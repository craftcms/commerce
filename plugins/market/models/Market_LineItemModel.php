<?php

namespace Craft;

/**
 * Class Market_LineItemModel
 * @package Craft
 *
 * @property int id
 * @property float price
 * @property float subtotal
 * @property float subtotalIncTax
 * @property float shipTotal
 * @property float total
 * @property float totalIncTax
 * @property int qty
 * @property int orderId
 * @property int variantId
 *
 * @property Market_OrderRecord order
 * @property Market_VariantRecord variant
 */
class Market_LineItemModel extends BaseModel
{
	public function getFinalAmount()
	{
		// TODO: $this->amount * $this->adjustmentTotal;
	}

	public function getOrder()
	{
		return craft()->market_order->getOrderById($this->orderId);
	}

	public function getVariant()
	{
		return craft()->market_variant->getById($this->variantId);
	}

	protected function defineAttributes()
	{
		return [
			'id' 			=> AttributeType::Number,
			'variantId' 	=> AttributeType::Number,
			'orderId' 		=> AttributeType::Number,
			'price' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'subtotal' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'subtotalIncTax'=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'shipTotal' 	=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'total' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'totalIncTax' 	=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'qty'   		=> [AttributeType::Number, 'min' => 0, 'required' => true],
		];
	}
}