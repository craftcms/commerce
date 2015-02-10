<?php

namespace Craft;

/**
 * Class Market_OrderService
 *
 * @package Craft
 */
class Market_OrderService extends BaseApplicationComponent
{

	protected $sessionCartId = "market_cart";
	private $cart;

	public function getCart()
	{
		if (NULL === $this->cart) {

			$number = $this->getSessionCartNumber();

			if ($number) {
				$cart       = $this->_getCartRecordByNumber($number);
				$this->cart = Market_OrderModel::populateModel($cart);
			}

			if (NULL === $this->cart) {
				$this->cart = new Market_OrderModel;
			}

			$this->cart->orderDate = DateTimeHelper::currentTimeForDb();
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
	 * @param $number
	 *
	 * @return \CActiveRecord
	 */
	private function _getCartRecordByNumber($number)
	{
		$cart = Market_OrderRecord::model()->with('items')->findByAttributes(
			['number' => $number],
			"completedAt = null"
		);

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
		$order = Market_OrderRecord::model()->findById($order->id);

		return $order->delete();
	}


	public function save($order)
	{
		$new = !$order->id;
		if ($new) {
			return $this->_createNewOrder($order);
		} else {
			return $this->_saveOrder($order);
		}
	}

	private function _createNewOrder($order)
	{
		$orderRecord         = new Market_OrderRecord();
		$orderRecord->typeId = $order->typeId;

		$orderRecord->validate();

		$order->addErrors($orderRecord->getErrors());

		if (!$order->hasErrors()) {
			if (\Craft\craft()->elements->saveElement($order)) {

				$number = \Market\Market::app()['hashids']->encode($order->id);
				// If you ever run out of hashids just change the R to something else lol.
				$orderRecord->number = 'R' . $number;
				$orderRecord->id     = $order->id;
				$orderRecord->save(false);

				return true;
			}
		}

		return false;
	}

	private function _saveOrder($order)
	{
		$orderRecord = Market_OrderRecord::model()->findById($order->id);

		if (!$orderRecord) {
			throw new Exception(Craft::t('No order exists with the ID “{id}”', array('id' => $order->id)));
		}

		if (\Craft\craft()->elements->saveElement($order)) {
			$orderRecord->typeId = $order->typeId;
			$orderRecord->save();

			return true;
		}

		return false;
	}

	private function _getSessionCartNumber()
	{
		return craft()->httpSession->get($this->sessionCartId);
	}

}