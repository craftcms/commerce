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
        $orderTypeHandle         = craft()->request->getPost('orderTypeHandle');
        $cart                    = craft()->market_cart->getCart($orderTypeHandle);

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
        $redirect                = craft()->request->getPost('redirect');
        $orderTypeHandle         = craft()->request->getPost('orderTypeHandle');
        $cart                    = craft()->market_cart->getCart($orderTypeHandle);

		//in case of success "pay" redirects us somewhere
		if (!craft()->market_payment->processPayment($cart, $paymentForm, $redirect, $customError)) {
			craft()->urlManager->setRouteVariables(compact('paymentForm', 'customError'));
		}
	}

    /**
     * Complete order
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionComplete()
    {
        $id = craft()->request->getParam('id');
        $transaction = craft()->market_transaction->getById($id);

        if(!$transaction->id) {
            throw new HttpException(400);
        }

        $order = $transaction->order;

        if($order->state != Market_OrderRecord::STATE_COMPLETE) {
            if ($order->canTransit(Market_OrderRecord::STATE_COMPLETE)) {
                craft()->market_order->complete($order);
            } else {
                throw new Exception('unable to go to complete state from the state: ' . $order->state);
            }
        }

        $this->redirect('market/orders');
    }
}
