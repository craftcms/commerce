<?php
namespace Craft;

/**
 * Class Commerce_PaymentController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CartPaymentController extends Commerce_BaseController
{
    protected $allowAnonymous = true;

    /**
     * @throws HttpException
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        $paymentForm = new Commerce_PaymentFormModel;
        $paymentForm->attributes = $_POST;
        // give the credit card number more of a chance to validate
        $paymentForm->number = preg_replace("/[^0-9]/", "", $paymentForm->number);
        $redirect = craft()->request->getPost('redirect');
        $cancelUrl = craft()->request->getPost('cancelUrl');
        $cart = craft()->commerce_cart->getCart();

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
        $transaction = craft()->commerce_transactions->getByHash($id);

        if (!$transaction->id) {
            throw new HttpException(400);
        }

        craft()->commerce_payments->completePayment($transaction);
    }
}
