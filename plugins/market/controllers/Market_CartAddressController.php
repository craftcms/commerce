<?php
namespace Craft;

/**
 * Cart. Step "Address".
 *
 * Class Market_CartAddressController
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

		$billing = new Market_AddressModel;
		$billing->attributes = craft()->request->getPost('BillingAddress');

		$shipping = new Market_AddressModel;
		$shipping->attributes = craft()->request->getPost('ShippingAddress');

		if (craft()->request->getPost('sameAddress') == 1) {
			$shipping = $billing;
		}

		if(craft()->market_order->setAddresses($shipping, $billing)) {
			$this->actionGoToPayment();
		} else {
			craft()->urlManager->setRouteVariables([
				'billingAddress' => $billing,
				'shippingAddress' => $shipping,
			]);
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

		$billingId = craft()->request->getPost('billingAddressId');
		$shippingId = craft()->request->getPost('shippingAddressId');

		$billingAddress = craft()->market_address->getById($billingId);
		$shippingAddress = craft()->market_address->getById($shippingId);

		if(!$billingAddress->id || !$shippingAddress->id) {
			$order = craft()->market_cart->getCart();
			if(empty($billingAddress->id)) {
				$order->addError('billingAddressId', 'Choose please billing address');
			}
			if(empty($shippingAddress->id)) {
				$order->addError('shippingAddressId', 'Choose please shipping address');
			}
			return;
		}

		if(craft()->market_order->setAddresses($shippingAddress, $billingAddress)) {
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

		$address = new Market_AddressModel;
		$address->attributes = craft()->request->getPost('Address');

		if(craft()->market_address->save($address)) {
			craft()->market_customer->saveAddress($address);
		} else {
			craft()->urlManager->setRouteVariables([
				'newAddress' => $address,
			]);
		}
	}

	/**
	 * Remove Address
	 * @throws HttpException
	 */
	public function actionRemoveAddress()
	{
		$this->requirePostRequest();

		$id = craft()->request->getPost('id', 0);

		if(!$id) {
			throw new HttpException(400);
		}

		craft()->market_address->deleteById($id);
	}

	/**
	 * @throws Exception
	 */
	public function actionGoToPayment()
	{
		$this->requirePostRequest();

		$order = craft()->market_cart->getCart();
		if(empty($order->shippingAddressId) || empty($order->billingAddressId)) {
			craft()->userSession->setNotice(Craft::t('Please fill shipping and billing addresses'));
			return;
		}

		if($order->canTransit(Market_OrderRecord::STATE_PAYMENT)) {
			$order->transition(Market_OrderRecord::STATE_PAYMENT);
		} else {
			throw new Exception('unable to go to payment state from the state: ' . $order->state);
		}
	}
}