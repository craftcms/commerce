<?php

namespace Craft;

use Market\Behaviors\Statemachine\AStateMachine;
use Market\Behaviors\Statemachine\AStateTransition;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderModel
 *
 * @property int                           $id
 * @property string                        $number
 * @property string                        couponCode
 * @property string                        $state
 * @property float                         $itemTotal
 * @property float                         finalPrice
 * @property float                         baseDiscount
 * @property float                         baseShippingRate
 * @property string                        $email
 * @property DateTime                      completedAt
 * @property string                        $lastIp
 * @property int                           typeId
 * @property int                           billingAddressId
 * @property int                           shippingAddressId
 * @property int                           shippingMethodId
 * @property int                           paymentMethodId
 * @property int                           customerId
 *
 * @property int                           totalQty
 * @property int                           totalWeight
 *
 * @property Market_OrderTypeModel         type
 * @property Market_LineItemModel[]        lineItems
 * @property Market_AddressModel           billingAddress
 * @property Market_CustomerModel          customer
 * @property Market_AddressModel           shippingAddress
 * @property Market_ShippingMethodModel    shippingMethod
 * @property Market_OrderAdjustmentModel[] adjustments
 * @property Market_PaymentMethodModel     paymentMethod
 * @property Market_TransactionModel[]     transactions
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
	 *
	 * @param string $name
	 * @param mixed  $behavior
	 *
	 * @return \IBehavior|mixed
	 */
	public function attachBehavior($name, $behavior)
	{
		$behavior = parent::attachBehavior($name, $behavior);
		if ($behavior instanceof AStateMachine) {
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

		return NULL;
	}

	/**
	 * @return array
	 */
	public function behaviors()
	{
		return [
			'state' => [
				'class'              => 'Market\Behaviors\Statemachine\AStateMachine',
				'states'             => [
					[
						'name'       => Market_OrderRecord::STATE_CART,
						'transitsTo' => Market_OrderRecord::STATE_ADDRESS
					], [
						'name'       => Market_OrderRecord::STATE_ADDRESS,
						'transitsTo' => [
							Market_OrderRecord::STATE_CART,
							Market_OrderRecord::STATE_ADDRESS,
							Market_OrderRecord::STATE_PAYMENT
						]
					], [
						'name'       => Market_OrderRecord::STATE_PAYMENT,
						'transitsTo' => [
							Market_OrderRecord::STATE_CART,
							Market_OrderRecord::STATE_ADDRESS,
							Market_OrderRecord::STATE_PAYMENT,
							Market_OrderRecord::STATE_CONFIRM,
							Market_OrderRecord::STATE_COMPLETE
						],
					], [
						'name'       => Market_OrderRecord::STATE_CONFIRM,
						'transitsTo' => Market_OrderRecord::STATE_COMPLETE
					], [
						'name' => Market_OrderRecord::STATE_COMPLETE,
					],
				],
				'defaultStateName'   => Market_OrderRecord::STATE_CART,
				'checkTransitionMap' => true,
				'stateName'          => $this->state,
			]
		];
	}

	public function isLocalized()
	{
		return false;
	}

	public function isEmpty()
	{
		return $this->getTotalQty() == 0;
	}

	/**
	 * @return int
	 */
	public function getTotalQty()
	{
		$qty = 0;
		foreach ($this->lineItems as $item) {
			$qty += $item->qty;
		}

		return $qty;
	}

	/**
	 * @return int
	 */
	public function getTotalWeight()
	{
		$weight = 0;
		foreach ($this->lineItems as $item) {
			$weight += $item->qty * $item->weight;
		}

		return $weight;
	}

	public function getAdjustments()
	{
		return craft()->market_orderAdjustment->getAllByOrderId($this->id);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'id'                => AttributeType::Number,
			'number'            => AttributeType::String,
			'couponCode'        => AttributeType::String,
			'state'             => [AttributeType::Enum, 'required' => true, 'default' => 'cart', 'values' => Market_OrderRecord::$states],
			'itemTotal'         => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'baseDiscount'      => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'baseShippingRate'  => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'finalPrice'        => [AttributeType::Number, 'decimals' => 4, 'default' => 0],
			'email'             => AttributeType::String,
			'completedAt'       => AttributeType::DateTime,
			'billingAddressId'  => AttributeType::Number,
			'shippingAddressId' => AttributeType::Number,
			'shippingMethodId'  => AttributeType::Number,
			'paymentMethodId'   => AttributeType::Number,
			'currency'          => AttributeType::String,
			'lastIp'            => AttributeType::String,
			'customerId'        => AttributeType::Number,
			'typeId'            => AttributeType::Number
		]);
	}
}