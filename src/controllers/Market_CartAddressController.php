<?php
namespace Craft;

/**
 * Cart. Step "Address".
 *
 * Class Market_CartAddressController
 *
 * @package Craft
 */
class Market_CartAddressController extends Market_BaseController
{
	protected $allowAnonymous = true;

	/**
	 * Posting two new addresses in case when a user has no saved address
	 *
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionPostTwoAddresses()
	{
		$this->requirePostRequest();

		$billing             = new Market_AddressModel;
		$billing->attributes = craft()->request->getPost('BillingAddress');

		$shipping             = new Market_AddressModel;
		$shipping->attributes = craft()->request->getPost('ShippingAddress');

		if (craft()->request->getPost('sameAddress') == 1) {
			$shipping = $billing;
		}

		$orderTypeHandle = craft()->request->getPost('orderTypeHandle');
		$order = craft()->market_cart->getCart($orderTypeHandle);

		if (craft()->market_order->setAddresses($order, $shipping, $billing)) {
			$this->actionGoToPayment();
		} else {
			craft()->urlManager->setRouteVariables([
				'billingAddress'  => $billing,
				'shippingAddress' => $shipping,
			]);
		}
	}

	/**
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionSetShippingMethod()
	{
		$this->requirePostRequest();

        $id              = craft()->request->getPost('shippingMethodId');
        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $cart            = craft()->market_cart->getCart($orderTypeHandle);

		if (craft()->market_cart->setShippingMethod($cart, $id)) {
			craft()->userSession->setFlash('market', 'Shipping method has been set');
			$this->redirectToPostedUrl();
		} else {
			craft()->urlManager->setRouteVariables(['shippingMethodError' => 'Wrong shipping method']);
		}
	}

	/**
	 * @throws Exception
	 */
	public function actionGoToPayment()
	{
		$this->requirePostRequest();

		$orderTypeHandle = craft()->request->getPost('orderTypeHandle');
		$order = craft()->market_cart->getCart($orderTypeHandle);

		if (empty($order->shippingAddressId) || empty($order->billingAddressId)) {
			craft()->userSession->setNotice(Craft::t('Please fill shipping and billing addresses'));

			return;
		}

		if ($order->canTransit(Market_OrderRecord::STATE_PAYMENT)) {
			$order->transition(Market_OrderRecord::STATE_PAYMENT);
		} else {
			throw new Exception('unable to go to payment state from the state: ' . $order->state);
		}
	}

	/**
	 * Choose Addresses
	 *
	 * @throws HttpException
	 * @throws \CHttpException
	 * @throws \Exception
	 */
	public function actionChooseAddresses()
	{
		$this->requirePostRequest();

		$billingId  = craft()->request->getPost('billingAddressId');
		$shippingId = craft()->request->getPost('shippingAddressId');

		$billingAddress  = craft()->market_address->getById($billingId);
		$shippingAddress = craft()->market_address->getById($shippingId);

		if (craft()->request->getPost('sameAddress') == 1) {
			$shippingAddress = $billingAddress;
		}

        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $order = craft()->market_cart->getCart($orderTypeHandle);

		if (!$billingAddress->id || !$shippingAddress->id) {
			if (empty($billingAddress->id)) {
				$order->addError('billingAddressId', 'Choose please billing address');
			}
			if (empty($shippingAddress->id)) {
				$order->addError('shippingAddressId', 'Choose please shipping address');
			}

			return;
		}

		if (craft()->market_order->setAddresses($order, $shippingAddress, $billingAddress)) {
			$this->actionGoToPayment();
		}
	}

	/**
	 * Add New Address
	 *
	 * @throws Exception
	 * @throws HttpException
	 */
	public function actionAddAddress()
	{
		$this->requirePostRequest();

		$address             = new Market_AddressModel;
		$address->attributes = craft()->request->getPost('Address');

		if (craft()->market_address->save($address)) {
			craft()->market_customer->saveAddress($address);
		} else {
			craft()->urlManager->setRouteVariables([
				'newAddress' => $address,
			]);
		}
	}

	/**
	 * Remove Address
	 *
	 * @throws HttpException
	 */
	public function actionRemoveAddress()
	{
		$this->requirePostRequest();

		$id = craft()->request->getPost('id', 0);

		if (!$id) {
			throw new HttpException(400);
		}

		craft()->market_address->deleteById($id);
	}
}