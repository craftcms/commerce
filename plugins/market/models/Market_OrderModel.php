<?php

namespace Craft;

/**
 * Class Market_OrderModel
 *
 * @property int    $id
 * @property string $number
 * @property string $state
 * @property float  $itemTotal
 * @property float  $adjustmentTotal
 * @property string $email
 * @property int    $userId
 * @property int    $orderDate
 * @property string	$lastIp
 * @package Craft
 */
class Market_OrderModel extends BaseElementModel
{
	const CART = 'cart';

	private $_orderItems;

	protected $elementType = 'Market_Order';

	public function isEditable()
	{
		return true;
	}

	public function __toString()
	{
		return $this->number;
	}

	public function getCpEditUrl()
	{
		$orderType = $this->getOrderType();

		return UrlHelper::getCpUrl('market/orders/' . $orderType->handle . '/' . $this->id);
	}

	public function getItems()
	{
		return craft()->market_lineItem->getAllByOrderId($this->id);
	}

	public function getOrderType()
	{
		return craft()->market_orderType->getById($this->typeId);
	}

	public function getFieldLayout()
	{
		if ($this->getOrderType()) {
			return $this->orderType->getFieldLayout();
		}

		return false;
	}

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'id'                  => AttributeType::Number,
			'number'              => AttributeType::String,
			'state'               => array(AttributeType::Enum, 'required' => true, 'values' => array('cart', 'address', 'delivery', 'payment', 'confirm', 'complete'), 'default' => 'cart'),
			'itemTotal'           => array(AttributeType::Number, 'decimals' => 4),
			'adjustmentTotal'     => array(AttributeType::Number, 'decimals' => 4),
			'email'               => AttributeType::String,
			'userId'              => AttributeType::Number,
			'completedAt'         => AttributeType::DateTime,
			'billingAddressId'    => AttributeType::Number,
			'shippingAddressId'   => AttributeType::Number,
			'specialInstructions' => AttributeType::String,
			'currency'            => AttributeType::String,
			'lastIp'              => AttributeType::String,
			'orderDate'           => AttributeType::DateTime,
			//TODO add 'shipmentState'
			//TODO add 'paymentState'
			'typeId'              => AttributeType::Number
		));
	}

	public function isLocalized()
	{
		return false;
	}

	public function recalculate()
	{

	}

	public function isEmpty()
	{
		return true;
	}

	function getTotal()
	{
		return $this->itemTotal + $this->adjustmentTotal;
	}

	public function getOutstandingBalance()
	{
		//TODO $this->total - $this->paymentTotal.
	}

	public function getDisplayItemTotal()
	{
		//TODO pretty version of total with currency and decimal markers.
	}

	public function getDisplayAdjustmentTotal()
	{
		//TODO pretty version of total with currency and decimal markers.
	}

	public function getDisplayTotal()
	{
		//TODO pretty version of total with currency and decimal markers.
	}

	public function getDisplayOutstandingBalance()
	{
		//TODO pretty version of OutstandingBalance with currency and decimal markers.
	}

}