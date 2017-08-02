<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\gateways\base\Gateway;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Payments Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class PaymentsController extends BaseFrontEndController
{
    /**
     * @return null|Response
     * @throws HttpException
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        $error = '';
        $customError = '';
        $order = null;

        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        if (($number = $request->getParam('orderNumber')) !== null) {
            $order = $plugin->getOrders()->getOrderByNumber($number);

            if (!$order) {
                $error = Craft::t('commerce', 'Can not find an order to pay.');

                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($error);
                }

                $session->setError($error);

                return null;
            }
        }

        // Get the cart if no order number was passed.
        if (!$order) {
            $order = $plugin->getCart()->getCart();
        }

        // Are we paying anonymously?
        $user = Craft::$app->getUser();

        if (!$order->isActiveCart() && !$user->checkPermission('commerce-manageOrders') && $plugin->getSettings()->requireEmailForAnonymousPayments) {
            if ($order->email !== $request->getParam('email')) {
                throw new HttpException(401, Craft::t("commerce", "Not authorized to make payments on this order."));
            }
        }

        if ($plugin->getSettings()->requireShippingAddressAtCheckout && !$order->shippingAddressId) {
            $error = Craft::t('commerce', 'Shipping address required.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        // These are used to compare if the order changed during it's final
        // recalculation before payment.
        $originalTotalPrice = $order->outstandingBalance();
        $originalTotalQty = $order->getTotalQty();
        $originalTotalAdjustments = count($order->getAdjustments());

        // Set guest email address onto guest customer and order.
        if (null !== $request->getParam('paymentCurrency')) {
            $currency = $request->getParam('paymentCurrency'); // empty string vs null (strict type checking)

            if (!$plugin->getCart()->setPaymentCurrency($order, $currency, $error)) {
                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($error);
                }

                $order->addError('paymentCurrency', $error);
                $session->setError($error);

                return null;
            }
        }

        // Allow setting the payment method at time of submitting payment.
        $gatewayId = $request->getParam('gatewayId');

        if ($gatewayId && $order->gatewayId != $gatewayId && !$plugin->getCart()->setGateway($order, $gatewayId, $error)) {
            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        /** @var Gateway $gateway */
        $gateway = $order->getGateway();

        if (!$gateway) {
            $error = Craft::t("commerce", "There is no gateway selected for this order.");

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentForm->setAttributes($request->getBodyParams(), false);

        // Allowed to update order's custom fields?
        if ($order->isActiveCart() || $user->checkPermission('commerce-manageOrders')) {
            $order->setFieldValuesFromRequest('fields');
        }

        // Check email address exists on order.
        if (!$order->email) {
            $customError = Craft::t("commerce", "No customer email address exists on this cart.");

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($customError);
            }

            $session->setError($customError);
            Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));

            return null;
        }

        // Save the return and cancel URLs to the order
        $returnUrl = $request->getValidatedBodyParam('redirect');
        $cancelUrl = $request->getValidatedBodyParam('cancelUrl');

        if ($returnUrl !== null || $cancelUrl !== null) {
            $view = Craft::$app->getView();
            $order->returnUrl = $view->renderObjectTemplate($returnUrl, $order);
            $order->cancelUrl = $view->renderObjectTemplate($cancelUrl, $order);
        }

        // Do one final save to confirm the price does not change out from under the customer.
        // This also confirms the products are available and discounts are current.
        if (Craft::$app->getElements()->saveElement($order)) {
            $totalPriceChanged = $originalTotalPrice != $order->outstandingBalance();
            $totalQtyChanged = $originalTotalQty != $order->getTotalQty();
            $totalAdjustmentsChanged = $originalTotalAdjustments != count($order->getAdjustments());

            // Has the order changed in a significant way?
            if ($totalPriceChanged || $totalQtyChanged || $totalAdjustmentsChanged) {
                if ($totalPriceChanged) {
                    $order->addError('totalPrice', Craft::t("commerce", "The total price of the order changed."));
                }

                if ($totalQtyChanged) {
                    $order->addError('totalQty', Craft::t("commerce", "The total quantity of items within the order changed."));
                }

                if ($totalAdjustmentsChanged) {
                    $order->addError('totalAdjustments', Craft::t("commerce", "The total number of order adjustments changed."));
                }

                $customError = Craft::t('commerce', 'Something changed with the order before payment, please review your order and submit payment again.');

                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($customError);
                }

                $session->setError($customError);
                Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));


                return null;
            }
        }

        $redirect = '';
        $paymentForm->validate();

        if (!$paymentForm->hasErrors()) {
            $success = $plugin->getPayments()->processPayment($order, $paymentForm, $redirect, $customError);
        } else {
            $customError = Craft::t('commerce', 'Payment information submitted is invalid.');
            $success = false;
        }

        if ($success) {
            if ($request->getAcceptsJson()) {
                $response = ['success' => true];
                if ($redirect) {
                    $response['redirect'] = $redirect;
                }
                return $this->asJson($response);
            }

            if ($redirect) {
                $this->redirect($redirect);
            } else {
                if ($order->returnUrl) {
                    $this->redirect($order->returnUrl);
                } else {
                    $this->redirectToPostedUrl($order);
                }
            }
        } else {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $customError, 'paymentForm' => $paymentForm->getErrors()]);
            }

            $session->setError($customError);
            Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Process return from off-site payment
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionCompletePayment()
    {
        $id = Craft::$app->getRequest()->getParam('commerceTransactionHash');

        $transaction = Plugin::getInstance()->getTransactions()->getTransactionByHash($id);

        if (!$transaction) {
            throw new HttpException(400, Craft::t("commerce", "Can not complete payment for missing transaction."));
        }

        $customError = "";
        $success = Plugin::getInstance()->getPayments()->completePayment($transaction, $customError);

        if ($success) {
            $this->redirect($transaction->order->returnUrl);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Payment error: {message}', ['message' => $customError]));
            $this->redirect($transaction->order->cancelUrl);
        }
    }

    /**
     * Process return from off-site payment
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionAcceptNotification()
    {
        $id = Craft::$app->getRequest()->getParam('commerceTransactionHash');

        Craft::info(json_encode($_REQUEST, JSON_PRETTY_PRINT), __METHOD__);

        Plugin::getInstance()->getPayments()->acceptNotification($id);
    }

}
