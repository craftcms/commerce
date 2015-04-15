<?php
namespace Craft;

/**
 * Cart. Step "Payment".
 *
 * Class Market_CartPaymentController
 *
 * @package Craft
 */
class Market_CartPaymentController extends Market_BaseController
{
	protected $allowAnonymous = true;

	/**
	 * @throws HttpException
	 */
	public function actionSetShippingMethod()
	{
		$this->requirePostRequest();

		$id = craft()->request->getPost('shippingMethodId');
		

		if (craft()->market_cart->setShippingMethod($id)) {
			craft()->userSession->setFlash('market', 'Shipping method has been set');
			$this->redirectToPostedUrl();
		} else {
			craft()->urlManager->setRouteVariables(['shippingMethodError' => 'Wrong shipping method']);
		}
	}

	/**
	 * @throws HttpException
	 */
	public function actionPay()
	{
		$this->requirePostRequest();

        $paymentForm             = new Market_PaymentFormModel;
        $paymentForm->attributes = $_POST;
        $orderTypeHandle         = craft()->request->getPost('orderTypeHandle');
        $cart                    = craft()->market_cart->getCart($orderTypeHandle);

		//in case of success "pay" redirects us somewhere
		if (!craft()->market_payment->processPayment($cart, $paymentForm, $customError)) {
			craft()->urlManager->setRouteVariables(compact('paymentForm', 'customError'));
		}
	}

	public function actionCancel()
	{
		$this->actionGoToComplete();
	}

	/**
	 * @throws Exception
	 */
	public function actionGoToComplete()
	{
		$orderTypeHandle = craft()->request->getPost('orderTypeHandle');
		$order = craft()->market_cart->getCart($orderTypeHandle);

		if ($order->canTransit(Market_OrderRecord::STATE_COMPLETE)) {
			craft()->market_order->complete($order);
		} else {
			throw new Exception('unable to go to payment state from the state: ' . $order->state);
		}
	}

	public function actionSuccess()
	{
		$this->actionGoToComplete();
	}
}
