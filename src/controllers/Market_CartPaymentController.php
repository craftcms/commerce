<?php
namespace Craft;

/**
 * Cart.
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

        $id              = craft()->request->getPost('shippingMethodId');
        $cart            = craft()->market_cart->getCart();

        if (craft()->market_cart->setShippingMethod($cart, $id)) {
            craft()->userSession->setFlash('notice',Craft::t('Shipping method has been set'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setFlash('notice',Craft::t('Wrong shipping method'));
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
        // give the credit card number more of a chance to validate
        $paymentForm->number = preg_replace("/[^0-9]/", "", $paymentForm->number);
        $redirect                = craft()->request->getPost('redirect');
        $cancelUrl               = craft()->request->getPost('cancelUrl');
        $cart                    = craft()->market_cart->getCart();

        if (!$cart->email){
            craft()->userSession->setFlash('error',Craft::t("No customer email address for cart."));
            craft()->urlManager->setRouteVariables(compact('paymentForm'));
            return;
        }

        // Ensure correct redirect urls are supplied.
        if (empty($cancelUrl) || empty($redirect)) {
            throw new Exception(Craft::t('Please specify "redirect" and "cancelUrl".'));
        }

        if (!craft()->market_payment->processPayment($cart, $paymentForm,
            $redirect, $cancelUrl, $customError)
        ) {
            craft()->userSession->setFlash('error',$customError);
            craft()->urlManager->setRouteVariables(compact('paymentForm'));
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
        $id          = craft()->request->getParam('hash');
        $transaction = craft()->market_transaction->getByHash($id);

        if (!$transaction->id) {
            throw new HttpException(400);
        }

        craft()->market_payment->completePayment($transaction);
    }
}
