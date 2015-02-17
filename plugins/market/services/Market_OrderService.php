<?php

namespace Craft;

/**
 * Class Market_OrderService
 *
 * @package Craft
 */
class Market_OrderService extends BaseApplicationComponent
{
	/** @var string Session key for storing current cart number */
	protected $sessionCartId = 'market_cart';
	/** @var Market_OrderModel */
	private $cart;

	/**
	 * @return Market_OrderModel
	 * @throws Exception
	 */
	public function getCart()
	{
		if (NULL === $this->cart) {
			$number = $this->_getSessionCartNumber();

			if($cart = $this->_getCartRecordByNumber($number)) {
				$this->cart = Market_OrderModel::populateModel($cart);
			} else {
				$this->cart = new Market_OrderModel;

				$orderType = craft()->market_orderType->getFirst();
				if(!$orderType->id) {
					throw new Exception('no one order type found');
				}

				$this->cart->typeId = $orderType->id;
			}

			$this->cart->lastIp    = craft()->request->getIpAddress();

//			TODO: Will need to see if current user changed and possibily recalc the cart
//			due to user specific discounts available to them.
//			$currentUser = craft()->userSession->user;
//			$userId      = (int)craft()->userSession->user->id;
//			if (!$this->cart->isEmpty() && (int)$this->cart->member_id != $member_id) {
//				// member_id has changed, reload the cart and save
//				$this->cart->userId = $userId;
//				$this->cart->recalculate();
//
//			}
		}

		return $this->cart;
	}

	/**
	 * @param $variantId
	 * @param $qty
	 * @param string $error
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function addToCart($variantId, $qty, &$error = '')
	{
		$transaction = craft()->db->beginTransaction();

		//getting current order
		$order = $this->getCart();
		if(!$order->id) {
			if (!$this->save($order)) {
				throw new Exception('Error on creating empty cart');
			}
		}

		//filling item model
		$lineItem = craft()->market_lineItem->getByOrderVariant($order->id, $variantId);
		if($lineItem->id) {
			$lineItem->qty += $qty;
		} else {
			$lineItem = craft()->market_lineItem->create($variantId, $order->id, $qty);
		}

		try {
			if(craft()->market_lineItem->save($lineItem)) {
				$this->recalculateOrder($order);
				$transaction->commit();
				return true;
			}
		} catch(\Exception $e) {
			$transaction->rollback();
			throw $e;
		}

		$transaction->rollback();

		$errors = $lineItem->getErrors();
		$first = array_pop($errors);
		$error = $first ? array_pop($first) : '';
		return false;
	}

	/**
	 * @TODO check that line item belongs to the current user
	 * @param int $lineItemId
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function removeFromCart($lineItemId)
	{
		$lineItem = craft()->market_lineItem->getById($lineItemId);

		if (!$lineItem->id) {
			throw new Exception('Line item not found');
		}

		$transaction = craft()->db->beginTransaction();
		try {
			craft()->market_lineItem->delete($lineItem);

			$order = $this->getCart();
			$this->recalculateOrder($order);
		} catch (\Exception $e) {
			$transaction->rollback();
			throw $e;
		}

		$transaction->commit();
	}

	/**
	 *
	 */
	public function clearCart()
	{
		$transaction = craft()->db->beginTransaction();
		try {
			$order = $this->getCart();
			craft()->market_lineItem->deleteAllByOrderId($order->id);
			$this->recalculateOrder($order);
		} catch (\Exception $e) {
			$transaction->rollback();
			throw $e;
		}

		$transaction->commit();
	}

	/**
	 * @param Market_OrderModel $order
	 */
	private function recalculateOrder(Market_OrderModel $order)
	{
		$lineItems = craft()->market_lineItem->getAllByOrderId($order->id);
		$order->itemTotal = array_reduce($lineItems, function($sum, $lineItem) {
			return $sum + $lineItem->totalIncTax;
		}, 0);

		$this->save($order);
	}

	/**
	 * @param string $number
	 * @return Market_OrderRecord
	 */
	private function _getCartRecordByNumber($number)
	{
		$cart = Market_OrderRecord::model()->findByAttributes([
			'number' => $number,
			'completedAt' => null,
		]);

		return $cart;
	}

	/**
	 * @param int $id
	 *
	 * @return Market_OrderModel
	 */
	public function getById($id)
	{
		$order = Market_OrderRecord::model()->findById($id);

		return Market_OrderModel::populateModel($order);
	}

	/**
	 * @param Market_OrderModel $order
	 *
	 * @return bool
	 * @throws \CDbException
	 */
	public function delete($order)
	{
		return Market_OrderRecord::model()->deleteByPk($order->id);
	}

	/**
	 * @param Market_OrderModel $order
	 * @return bool
	 * @throws Exception
	 */
	public function save($order)
	{
		if (!$order->id) {
			$orderRecord = new Market_OrderRecord();
		} else {
			$orderRecord = Market_OrderRecord::model()->findById($order->id);

			if (!$orderRecord) {
				throw new Exception(Craft::t('No order exists with the ID “{id}”', array('id' => $order->id)));
			}
		}

		$orderRecord->typeId 			= $order->typeId;
		$orderRecord->number 			= $this->_getSessionCartNumber();
		$orderRecord->adjustmentTotal 	= $order->adjustmentTotal;
		$orderRecord->itemTotal 		= $order->itemTotal;
		$orderRecord->email 			= $order->email;
		$orderRecord->completedAt 		= $order->completedAt;
		$orderRecord->billingAddressId 	= $order->billingAddressId;
		$orderRecord->shippingAddressId = $order->shippingAddressId;
		$orderRecord->state 			= $order->state;

		$orderRecord->validate();
		$order->addErrors($orderRecord->getErrors());

		if (!$order->hasErrors()) {
			if (craft()->elements->saveElement($order)) {
				$orderRecord->id     = $order->id;
				$orderRecord->save(false);

				return true;
			}
		}
		return false;
	}

	/**
	 * @return string
	 */
	private function _getSessionCartNumber()
	{
		$cartNumber = craft()->httpSession->get($this->sessionCartId);

		if(!$cartNumber) {
			$cartNumber = md5(uniqid(mt_rand(), true));
			craft()->httpSession->add($this->sessionCartId, $cartNumber);
		}

		return $cartNumber;
	}

}