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

		$shipping = new Market_AddressModel;
		$billing = new Market_AddressModel;

		$shipping->attributes = craft()->request->getPost('ShippingAddress');
		$billing->attributes = craft()->request->getPost('BillingAddress');

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
			throw new \CHttpException(400);
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
		die('going to payment');
//		$this->requirePostRequest();
//
//		$order = craft()->market_order->getCart();
//		if(empty($order->lineItems)) {
//			craft()->userSession->setNotice(Craft::t('Please add some items to your cart'));
//			return;
//		}
//
//		if($order->canTransit(Market_OrderRecord::STATE_ADDRESS)) {
//			$order->transition(Market_OrderRecord::STATE_ADDRESS);
//			$this->redirectToPostedUrl();
//		} else {
//			throw new Exception('unable to go to address state from the state: ' . $order->state);
//		}
	}
}