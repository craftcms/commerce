<?php

namespace Craft;
use Market\Behaviors\Statemachine\AStateMachine;
use Market\Behaviors\Statemachine\AStateTransition;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderModel
 *
 * @property int    $id
 * @property string $number
 * @property string couponCode
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
 * @property Market_OrderTypeModel type
 * @property Market_LineItemModel[] lineItems
 * @property Market_AddressModel billingAddress
 * @property Market_AddressModel shippingAddress
 *
 * @method bool canTransit(string $state)
 * @method void transition(string $state)
 *
 * @package Craft
 */
class Market_OrderModel extends BaseElementModel
{
    use Market_ModelRelationsTrait;

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
		$orderType = $this->type;
		return UrlHelper::getCpUrl('market/orders/' . $orderType->handle . '/' . $this->id);
	}

	/**
	 * @return null|FieldLayoutModel
	 */
	public function getFieldLayout()
	{
		if ($this->type) {
			return $this->type->getFieldLayout();
		}

		return null;
	}

    /**
     * @return array
     */
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

    /**
     * @return array
     */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'id'                  => AttributeType::Number,
			'number'              => AttributeType::String,
			'couponCode'          => AttributeType::String,
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