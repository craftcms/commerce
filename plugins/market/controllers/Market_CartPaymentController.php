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
	public function actionPay()
	{
		$this->requirePostRequest();

		$paymentForm             = new Market_PaymentFormModel;
		$paymentForm->attributes = $_POST;
        $cart = craft()->market_cart->getCart();

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
		$order = craft()->market_cart->getCart();

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