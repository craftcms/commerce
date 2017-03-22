<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\web\HttpException;

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
     * @throws HttpException
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        $customError = '';

        if (($number = Craft::$app->getRequest()->getParam('orderNumber')) !== null) {
            $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);
            if (!$order) {
                $error = Craft::t('commerce', 'Can not find an order to pay.');
                if (Craft::$app->getRequest()->isAjax()) {
                    $this->asErrorJson($error);
                } else {
                    Craft::$app->getUser()->setFlash('error', $error);
                }

                return;
            }
        }

        // Get the cart if no order number was passed.
        if (!isset($order) && !$number) {
            $order = Plugin::getInstance()->getCart()->getCart();
        }

        // Are we paying anonymously?
        if (!$order->isActiveCart() && !Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            if (Craft::$app->getConfig()->get('requireEmailForAnonymousPayments', 'commerce')) {
                if ($order->email !== Craft::$app->getRequest()->getParam('email')) {
                    throw new HttpException(401, Craft::t("commerce", "Not authorized to make payments on this order."));
                }
            }
        }

        if (Craft::$app->getConfig()->get('requireShippingAddressAtCheckout', 'commerce')) {
            if (!$order->shippingAddressId) {
                $error = Craft::t('commerce', 'Shipping address required.');
                if (Craft::$app->getRequest()->isAjax()) {
                    $this->asErrorJson($error);
                } else {
                    Craft::$app->getUser()->setFlash('error', $error);
                }

                return;
            }
        }

        // These are used to compare if the order changed during it's final
        // recalculation before payment.
        $originalTotalPrice = $order->outstandingBalance();
        $originalTotalQty = $order->getTotalQty();
        $originalTotalAdjustments = count($order->getAdjustments());

        // Set guest email address onto guest customer and order.
        if (!is_null(Craft::$app->getRequest()->getParam('paymentCurrency'))) {
            $currency = Craft::$app->getRequest()->getParam('paymentCurrency'); // empty string vs null (strict type checking)
            $error = '';
            if (!Plugin::getInstance()->getCart()->setPaymentCurrency($order, $currency, $error)) {
                if (Craft::$app->getRequest()->isAjax()) {
                    $this->asErrorJson($error);
                } else {
                    $order->addError('paymentCurrency', $error);
                    Craft::$app->getUser()->setFlash('error', $error);
                }

                return;
            }
        }

        // Allow setting the payment method at time of submitting payment.
        $paymentMethodId = Craft::$app->getRequest()->getParam('paymentMethodId');
        if ($paymentMethodId && $order->paymentMethodId != $paymentMethodId) {
            $error = "";
            if (!Plugin::getInstance()->getCart()->setPaymentMethod($order, $paymentMethodId, $error)) {
                if (Craft::$app->getRequest()->isAjax()) {
                    $this->asErrorJson($error);
                } else {
                    Craft::$app->getUser()->setFlash('error', $error);
                }

                return;
            }
        }

        $paymentMethod = $order->getPaymentMethod();

        if (!$paymentMethod) {
            $error = Craft::t("commerce", "There is no payment method selected for this order.");
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asErrorJson($error);
            } else {
                Craft::$app->getUser()->setFlash('error', $error);
            }

            return;
        }

        // Get the payment method' gateway adapter's expected form model
        $paymentForm = $paymentMethod->getPaymentFormModel();
        $paymentForm->populateModelFromPost(Craft::$app->getRequest()->getParam());

        // Allowed to update order's custom fields?
        if ($order->isActiveCart() || Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $order->setContentFromPost('fields');
        }

        // Check email address exists on order.
        if (!$order->email) {
            $customError = Craft::t("commerce", "No customer email address exists on this cart.");
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asErrorJson($customError);
            } else {
                Craft::$app->getUser()->setFlash('error', $customError);
                Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));
            }

            return;
        }

        // Save the return and cancel URLs to the order
        $returnUrl = Craft::$app->getRequest()->getValidatedPost('redirect');
        $cancelUrl = Craft::$app->getRequest()->getValidatedPost('cancelUrl');

        if ($returnUrl !== null || $cancelUrl !== null) {
            $order->returnUrl = Craft::$app->getView()->renderObjectTemplate($returnUrl, $order);
            $order->cancelUrl = Craft::$app->getView()->renderObjectTemplate($cancelUrl, $order);
        }

        // Do one final save to confirm the price does not change out from under the customer.
        // This also confirms the products are available and discounts are current.
        if (Plugin::getInstance()->getOrders()->saveOrder($order)) {
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
                if (Craft::$app->getRequest()->isAjax()) {
                    $this->asErrorJson($customError);
                } else {
                    Craft::$app->getUser()->setFlash('error', $customError);
                    Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));
                }

                return;
            }
        }

        $redirect = "";
        $paymentForm->validate();
        if (!$paymentForm->hasErrors()) {
            $success = Plugin::getInstance()->getPayments()->processPayment($order, $paymentForm, $redirect, $customError);
        } else {
            $customError = Craft::t('commerce', 'Payment information submitted is invalid.');
            $success = false;
        }


        if ($success) {
            if (Craft::$app->getRequest()->isAjax()) {
                $response = ['success' => true];
                if ($redirect) {
                    $response['redirect'] = $redirect;
                }
                $this->asJson($response);
            } else {
                if ($redirect) {
                    $this->redirect($redirect);
                } else {
                    if ($order->returnUrl) {
                        $this->redirect($order->returnUrl);
                    } else {
                        $this->redirectToPostedUrl($order);
                    }
                }
            }
        } else {
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asJson(['error' => $customError, 'paymentForm' => $paymentForm->getErrors()]);
            } else {
                Craft::$app->getSession()->setError($customError);
                Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));
            }
        }
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

        CommercePlugin::log(json_encode($_REQUEST, JSON_PRETTY_PRINT));

        Plugin::getInstance()->getPayments()->acceptNotification($id);
    }

}
