<?php

namespace Craft;

class Market_LineItemModel extends BaseModel
{

	public function getAmount()
	{
		$this->price * $this->quantity;
	}

	public function getFinalAmount()
	{
		// TODO: $this->amount * $this->adjustmentTotal;
	}

	public function getOrder()
	{
		//TODO return craft()->market_order->getOrderById($this->orderId);
	}

	public function getVariant()
	{
		return craft()->market_variant->getById($this->variantId);
	}

	public function getProduct()
	{
		return $this->variant->product;
	}

	protected function defineAttributes()
	{
		return array(
			'id'        => AttributeType::Number,
			'orderId'   => AttributeType::Number,
			'variantId' => AttributeType::Number,
			'price'     => array(AttributeType::Number, 'decimals' => 4),
			'quantity'  => AttributeType::Number
		);
	}
}