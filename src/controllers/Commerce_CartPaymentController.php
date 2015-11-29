<?php
namespace Craft;

/**
 * Class Commerce_PaymentController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CartPaymentController extends Commerce_BaseFrontEndController
{
    /**
     * @throws HttpException
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        $customError = '';

        $paymentForm = new Commerce_PaymentFormModel;
        $paymentForm->firstName = craft()->request->getParam('firstName');
        $paymentForm->lastName = craft()->request->getParam('lastName');
        // give the credit card number more of a chance to validate
        $paymentForm->number = preg_replace("/[^0-9]/", "", craft()->request->getParam('number'));
        $paymentForm->month = craft()->request->getParam('month');
        $paymentForm->year = craft()->request->getParam('year');
        $paymentForm->cvv = craft()->request->getParam('cvv');
        $paymentForm->token = craft()->request->getParam('token');

        // Let's be nice and allow 'stripeToken' to be used as 'token', since it is the checkout.js default.
        $stripeToken = craft()->request->getParam('stripeToken');
        if($stripeToken){
            $paymentForm->token = $stripeToken;
        }

        $redirect = craft()->request->getPost('redirect');
        $cancelUrl = craft()->request->getPost('cancelUrl');

        $cart = craft()->commerce_cart->getCart();

        $cart->setContentFromPost('fields');

        $paymentMethodId = craft()->request->getParam('paymentMethodId');
        if($paymentMethodId){
            if (!craft()->commerce_cart->setPaymentMethod($cart, $paymentMethodId, $error)) {
                craft()->userSession->setFlash('error', $error);
                craft()->urlManager->setRouteVariables(compact('paymentForm'));
                return;
            }
        }

        if (!$cart->email) {
            craft()->userSession->setFlash('error', Craft::t("No customer email address for cart."));
            craft()->urlManager->setRouteVariables(compact('paymentForm'));
            return;
        }

        // Ensure correct redirect urls are supplied.
        if (empty($cancelUrl) || empty($redirect)) {
            throw new Exception(Craft::t('Please specify "redirect" and "cancelUrl".'));
        }

        if (!craft()->commerce_payments->processPayment($cart, $paymentForm,
            $redirect, $cancelUrl, $customError)
        ) {
            craft()->userSession->setFlash('error', $customError);
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
        $id = craft()->request->getParam('hash');
        $transaction = craft()->commerce_transactions->getTransactionByHash($id);

        if (!$transaction->id) {
            throw new HttpException(400);
        }

        craft()->commerce_payments->completePayment($transaction);
    }
}
