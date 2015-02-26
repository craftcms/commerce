<?php
namespace Craft;

/**
 * Cart. Step "Payment".
 *
 * Class Market_CartPaymentController
 * @package Craft
 */
class Market_CartPaymentController extends Market_BaseController
{
	protected $allowAnonymous = true;

	/**
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionSetShippingMethod()
	{
		$this->requirePostRequest();

		$id = craft()->request->getPost('shippingMethodId');
        if(craft()->market_cart->setShippingMethod($id)) {
            craft()->userSession->setFlash('market', 'Shipping method has been set');
            $this->redirectToPostedUrl();
        } else {
            craft()->urlManager->setRouteVariables(['shippingMethodError' => 'Wrong shipping method']);
        }
	}
	/**
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionSetPaymentMethod()
	{
		$this->requirePostRequest();

		$id = craft()->request->getPost('paymentMethodId');
        if(craft()->market_cart->setPaymentMethod($id)) {
            craft()->userSession->setFlash('market', 'Payment method has been set');
            $this->redirectToPostedUrl();
        } else {
            craft()->urlManager->setRouteVariables(['paymentMethodError' => 'Wrong payment method']);
        }
	}

    /**
     * @throws HttpException
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        $paymentForm = new Market_PaymentFormModel;
        $paymentForm->attributes = $_POST;

        if(craft()->market_cart->pay($paymentForm)) {
            craft()->userSession->setFlash('market', 'Successful payment');
            $this->redirectToPostedUrl();
        } else {
            craft()->urlManager->setRouteVariables(['paymentForm' => $paymentForm]);
        }
    }
}