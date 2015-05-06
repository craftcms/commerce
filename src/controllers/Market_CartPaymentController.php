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
	 * @throws HttpException
	 */
	public function actionPay()
	{
		$this->requirePostRequest();

        $paymentForm             = new Market_PaymentFormModel;
        $paymentForm->attributes = $_POST;
        $returnUrl               = craft()->request->getPost('returnUrl');
        $cancelUrl               = craft()->request->getPost('cancelUrl');
        $orderTypeHandle         = craft()->request->getPost('orderTypeHandle');
        $cart                    = craft()->market_cart->getCart($orderTypeHandle);

		// Ensure correct redirect urls are supplied.
		$redirect = craft()->request->getPost('redirect');
		if(empty($returnUrl) || empty($cancelUrl) || !empty($redirect)) {
			throw new Exception('Please specify "returnUrl" and "cancelUrl". "redirect" param is not allowed in this action');
		}


		//in case of success "pay" redirects us somewhere
		if (!craft()->market_payment->processPayment($cart, $paymentForm, $returnUrl, $cancelUrl, $customError)) {
			craft()->urlManager->setRouteVariables(compact('paymentForm', 'customError'));
		}
	}

    /**
     * Process return from off-site payment
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionComplete()
    {
        $id = craft()->request->getParam('hash');
        $transaction = craft()->market_transaction->getByHash($id);

        if(!$transaction->id) {
            throw new HttpException(400);
        }

        craft()->market_payment->completePayment($transaction);
    }
}
