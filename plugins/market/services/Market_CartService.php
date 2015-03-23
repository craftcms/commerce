<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Cart is same as Order. This class deals with order as with cart. All saving
 * logic and etc. are in OrderService
 *
 * Class Market_CartService
 *
 * @package Craft
 */
class Market_CartService extends BaseApplicationComponent
{
	const CART_COOKIE_LIFETIME = 604800; //week

	/** @var string Session key for storing current cart number */
	protected $cookieCartId = 'market_cookie';
	/** @var Market_OrderModel */
	private $cart;

	/**
	 * @param        $variantId
	 * @param        $qty
	 * @param string $cartHandle
	 * @param string $error
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function addToCart($variantId, $qty, &$error = '')
	{
		MarketDbHelper::beginStackedTransaction();

		//getting current order
		$order = $this->getCart();
		if (!$order->id) {
			if (!craft()->market_order->save($order)) {
				throw new Exception('Error on creating empty cart');
			}
		}

		//filling item model
		$lineItem = craft()->market_lineItem->getByOrderVariant($order->id, $variantId);

		if ($lineItem->id) {
			$lineItem->qty += $qty;
		} else {
			$lineItem = craft()->market_lineItem->create($variantId, $order->id, $qty);
		}

		try {
			if (craft()->market_lineItem->save($lineItem)) {
				craft()->market_order->save($order);
				MarketDbHelper::commitStackedTransaction();

				return true;
			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::rollbackStackedTransaction();

		$errors = $lineItem->getAllErrors();
		$error  = array_pop($errors);

		return false;
	}

	/**
	 * @return Market_OrderModel
	 * @throws Exception
	 */
	public function getCart($orderTypeHandle)
	{

		// Before getting the cart, make sure we have a real orderType and then
		// get the first one if we cant find one. Will fail loud if no order types.
		$orderType = craft()->market_orderType->getByHandleOrOnly($orderTypeHandle);

		// Should only be dealing with legit order types now
		if (!isset($this->cart[$orderType->handle])) {

			$number = $this->_getSessionCartNumber($orderType->handle);

			if ($cart = $this->_getCartRecordByNumber($number)) {
				$this->cart[$orderType->handle] = Market_OrderModel::populateModel($cart);
			} else {
				$this->cart[$orderType->handle] = new Market_OrderModel;
				$this->cart[$orderType->handle]->typeId = $orderType->id;
				$this->cart[$orderType->handle]->number = $number;
			}

			$this->cart[$orderType->handle]->lastIp = craft()->request->getIpAddress();

			// Update the user if it has changed
			$customer = craft()->market_customer->getCustomer();
			if (!$this->cart[$orderType->handle]->isEmpty() && $this->cart[$orderType->handle]->customerId != $customer->id) {
				$this->cart[$orderType->handle]->customerId = $customer->id;
				craft()->market_order->save($this->cart[$orderType->handle]);
			}
		}

		return $this->cart[$orderType->handle];
	}

	/**
	 * @return string
	 */
	private function _getSessionCartNumber($cartHandle)
	{
		$cookieId = $cartHandle."_".$this->cookieCartId;
		$cartNumber = craft()->userSession->getStateCookieValue($cookieId);

		if (!$cartNumber) {
			$cartNumber = md5(uniqid(mt_rand(), true));
			craft()->userSession->saveCookie($cookieId, $cartNumber, self::CART_COOKIE_LIFETIME);
		}

		return $cartNumber;
	}

	/**
	 * @param string $number
	 *
	 * @return Market_OrderRecord
	 */
	private function _getCartRecordByNumber($number)
	{
		$cart = Market_OrderRecord::model()->findByAttributes([
			'number'      => $number,
			'completedAt' => NULL,
		]);

		return $cart;
	}

	/**
	 * @param string $code
	 * @param string $error
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function applyCoupon($code, &$error = '')
	{
		$cart = $this->getCart();

		if (empty($code) || craft()->market_discount->checkCode($code, $error)) {
			$cart->couponCode = $code ?: NULL;
			craft()->market_order->save($cart);

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set shipping method to the current order
	 *
	 * @param int $shippingMethodId
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function setShippingMethod($shippingMethodId)
	{
		$method = craft()->market_shippingMethod->getById($shippingMethodId);
		if (!$method->id) {
			return false;
		}

		$cart = $this->getCart();
		if (!craft()->market_shippingMethod->getMatchingRule($cart, $method)) {
			return false;
		}

		$cart->shippingMethodId = $shippingMethodId;
		craft()->market_order->save($cart);

		return true;
	}

	public function forgetCart()
	{
		craft()->userSession->deleteStateCookie($this->cookieCartId);
	}

	/**
	 * Set shipping method to the current order
	 *
	 * @param int $paymentMethodId
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function setPaymentMethod($paymentMethodId)
	{
		$method = craft()->market_paymentMethod->getById($paymentMethodId);
		if (!$method->id || !$method->frontendEnabled) {
			return false;
		}

		$cart                  = $this->getCart();
		$cart->paymentMethodId = $paymentMethodId;
		craft()->market_order->save($cart);

		return true;
	}

	/**
	 * @TODO check that line item belongs to the current user
	 *
	 * @param int $lineItemId
	 *
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

		MarketDbHelper::beginStackedTransaction();
		try {
			craft()->market_lineItem->delete($lineItem);

			$order = $this->getCart();
			craft()->market_order->save($order);
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::commitStackedTransaction();
	}

	/**
	 * Remove all items
	 */
	public function clearCart()
	{
		MarketDbHelper::beginStackedTransaction();
		try {
			$order = $this->getCart();
			craft()->market_lineItem->deleteAllByOrderId($order->id);
			craft()->market_order->save($order);
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::commitStackedTransaction();
	}
}
