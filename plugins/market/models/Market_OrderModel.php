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
 * @property DateTime completedAt
 * @property string	$lastIp
 * @property int    typeId
 * @property int    billingAddressId
 * @property int    shippingAddressId
 *
 * @property Market_OrderTypeRecord type
 * @property Market_LineItemRecord[] lineItems
 * @property Market_AddressRecord billingAddress
 * @property Market_AddressRecord shipmentAddress
 *
 * @package Craft
 */
class Market_OrderModel extends BaseElementModel
{
	const CART = 'cart';

	private $_orderItems = array();
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
		$orderType = $this->getType();

		return UrlHelper::getCpUrl('market/orders/' . $orderType->handle . '/' . $this->id);
	}

	public function getLineItems()
	{
		if (!$this->_orderItems && $this->id){
			$this->_orderItems = craft()->market_lineItem->getAllByOrderId($this->id);
		}

		return $this->_orderItems;
	}

	public function getType()
	{
		return craft()->market_orderType->getById($this->typeId);
	}

	public function getFieldLayout()
	{
		if ($this->getType()) {
			return $this->type->getFieldLayout();
		}

		return false;
	}

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'id'                  => AttributeType::Number,
			'number'              => AttributeType::String,
			'state'               => [AttributeType::Enum, 'required' => true, 'default' => 'cart', 'values' => ['cart', 'address', 'delivery', 'payment', 'confirm', 'complete']],
			'itemTotal'           => [AttributeType::Number, 'decimals' => 4],
			'adjustmentTotal'     => [AttributeType::Number, 'decimals' => 4],
			'email'               => AttributeType::String,
			'completedAt'         => AttributeType::DateTime,
			'billingAddressId'    => AttributeType::Number,
			'shippingAddressId'   => AttributeType::Number,
			'currency'            => AttributeType::String,
			'lastIp'              => AttributeType::String,
			'orderDate'           => AttributeType::DateTime,
			//TODO add 'shipmentState'
			//TODO add 'paymentState'
			'typeId'              => AttributeType::Number
		]);
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