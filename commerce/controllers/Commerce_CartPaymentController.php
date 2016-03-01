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

        $cart = craft()->commerce_cart->getCart();
        $cart->setContentFromPost('fields');

        $paymentMethodId = craft()->request->getParam('paymentMethodId');
        if($paymentMethodId){
            if (!craft()->commerce_cart->setPaymentMethod($cart, $paymentMethodId, $error)) {
                if (craft()->request->isAjaxRequest()) {
                    $this->returnErrorJson($error);
                } else {
                    craft()->userSession->setFlash('error', $error);
                    craft()->urlManager->setRouteVariables(compact('paymentForm'));
                }
                return;
            }
        }

        if (!$cart->email) {
            $customError = Craft::t("No customer email address exists on this cart.");
            if (craft()->request->isAjaxRequest()) {
                $this->returnErrorJson($customError);
            } else {
                craft()->userSession->setFlash('error', $customError);
                craft()->urlManager->setRouteVariables(compact('paymentForm'));
            }
            return;
        }

        // Save the return and cancel URLs to the cart
        $returnUrl = craft()->request->getPost('redirect');
        $cancelUrl = craft()->request->getPost('cancelUrl');

        if ($returnUrl !== null || $cancelUrl !== null) {
            $cart->returnUrl = craft()->templates->renderObjectTemplate($returnUrl, $cart);
            $cart->cancelUrl = craft()->templates->renderObjectTemplate($cancelUrl, $cart);
            craft()->commerce_orders->saveOrder($cart);
        }

        $success = craft()->commerce_payments->processPayment($cart, $paymentForm, $redirect, $customError);

        if ($success) {
            if (craft()->request->isAjaxRequest()) {
                $response = ['success' => true];
                if ($redirect !== null) {
                    $response['redirect'] = $redirect;
                }
                $this->returnJson($response);
            } else {
                if ($redirect !== null) {
                    $this->redirect($redirect);
                } else {
                    $this->redirectToPostedUrl($cart);
                }
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnErrorJson($customError);
            } else {
                craft()->userSession->setFlash('error', $customError);
                craft()->urlManager->setRouteVariables(compact('paymentForm'));
            }
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

        if (!$transaction) {
            throw new HttpException(400,Craft::t("Can not complete payment for missing transaction."));
        }

        $success = craft()->commerce_payments->completePayment($transaction, $customError);

        if ($success) {
            $this->redirect($transaction->order->returnUrl);
        } else {
            craft()->userSession->setError(Craft::t('Payment error: {message}', ['message' => $customError]));
            $this->redirect($transaction->order->cancelUrl);
        }
    }
}
