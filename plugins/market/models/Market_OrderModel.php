<?php

namespace Craft;
use Market\Behaviors\Statemachine\AStateMachine;
use Market\Behaviors\Statemachine\AStateTransition;

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
 * @method bool canTransit(string $state)
 * @method void transition(string $state)
 *
 * @package Craft
 */
class Market_OrderModel extends BaseElementModel
{
	const CART = 'cart';

	private $_orderItems = array();
	protected $elementType = 'Market_Order';

	/**
	 * Attaching event to behaviour
	 * @param string $name
	 * @param mixed $behavior
	 * @return \IBehavior|mixed
	 */
	public function attachBehavior($name, $behavior)
	{
		$behavior = parent::attachBehavior($name, $behavior);
		if($behavior instanceof AStateMachine) {
			$behavior->onAfterTransition = [$this, 'onStateChange'];
		}
		return $behavior;
	}

	/**
	 * @param AStateTransition $transition
	 */
	public function onStateChange(AStateTransition $transition)
	{
		$this->state = $transition->to->getName();
		craft()->market_order->save($this);
	}

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

	/**
	 * @return Market_LineItemModel[]
	 */
	public function getLineItems()
	{
		if (!$this->_orderItems && $this->id){
			$this->_orderItems = craft()->market_lineItem->getAllByOrderId($this->id);
		}

		return $this->_orderItems;
	}

	/**
	 * @return Market_OrderTypeModel
	 */
	public function getType()
	{
		return craft()->market_orderType->getById($this->typeId);
	}

	/**
	 * @return false|FieldLayoutModel
	 */
	public function getFieldLayout()
	{
		if ($this->getType()) {
			return $this->type->getFieldLayout();
		}

		return false;
	}

	public function behaviors()
	{
		return [
			'state' => [
				'class' => 'Market\Behaviors\Statemachine\AStateMachine',
				'states' => [[
						'name' => Market_OrderRecord::STATE_CART,
						'transitsTo' => Market_OrderRecord::STATE_ADDRESS
					], [
						'name' => Market_OrderRecord::STATE_ADDRESS,
						'transitsTo' => Market_OrderRecord::STATE_PAYMENT
					], [
						'name' => Market_OrderRecord::STATE_PAYMENT,
						'transitsTo' => Market_OrderRecord::STATE_CONFIRM
					], [
						'name' => Market_OrderRecord::STATE_CONFIRM,
						'transitsTo' => Market_OrderRecord::STATE_COMPLETE
					], [
						'name' => Market_OrderRecord::STATE_COMPLETE,
					],
				],
				'defaultStateName' => Market_OrderRecord::STATE_CART,
				'checkTransitionMap' => true,
				'stateName' => $this->state,
			]
		];
	}


	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'id'                  => AttributeType::Number,
			'number'              => AttributeType::String,
			'state'               => [AttributeType::Enum, 'required' => true, 'default' => 'cart', 'values' => Market_OrderRecord::$states],
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