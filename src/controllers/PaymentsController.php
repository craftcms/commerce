<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Payments Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentsController extends BaseFrontEndController
{
    private $_cartVariableName;


    public function init()
    {
        $this->_cartVariableName = Plugin::getInstance()->getSettings()->cartVariable;

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function beforeAction($action): bool
    {
        // Don't enable CSRF validation for complete-payment requests
        if ($action->id === 'complete-payment') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * @return Response|null
     * @throws HttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPay()
    {
        $this->requirePostRequest();

        $customError = '';

        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $isSiteRequest = Craft::$app->getRequest()->getIsSiteRequest();
        $userSession = Craft::$app->getUser();

        if (($number = $request->getBodyParam('orderNumber')) !== null) {
            /** @var Order $order */
            $order = $plugin->getOrders()->getOrderByNumber($number);

            if (!$order) {
                $error = Plugin::t('Can not find an order to pay.');

                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($error);
                }

                $session->setError($error);

                return null;
            }
        } else {
            /** @var Order $order */
            $order = $plugin->getCarts()->getCart(true);
        }

        $cartActiveAndHasPermission = !$order->getIsActiveCart() && !$currentUser->can('commerce-manageOrders');
        if ($cartActiveAndHasPermission && $order->getEmail() !== $request->getParam('email')) {
            $error = Plugin::t('Email required to make payments on a completed order.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        if ($plugin->getSettings()->requireShippingAddressAtCheckout && !$order->shippingAddressId) {
            $error = Plugin::t('Shipping address required.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        if ($plugin->getSettings()->requireBillingAddressAtCheckout && !$order->billingAddressId) {
            $error = Plugin::t('Billing address required.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        if (!$plugin->getSettings()->allowEmptyCartOnCheckout && $order->getIsEmpty()) {
            $error = Plugin::t('Order can not be empty.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        // Set if the customer should be registered on order completion
        if ($request->getBodyParam('registerUserOnOrderComplete')) {
            $order->registerUserOnOrderComplete = true;
        }

        // These are used to compare if the order changed during its final
        // recalculation before payment.
        $originalTotalPrice = $order->getOutstandingBalance();
        $originalTotalQty = $order->getTotalQty();
        $originalTotalAdjustments = count($order->getAdjustments());

        // Set guest email address onto guest customer and order.
        if (null !== $request->getParam('paymentCurrency')) {
            $currency = $request->getParam('paymentCurrency'); // empty string vs null (strict type checking)

            try {
                $order->setPaymentCurrency($currency);
            } catch (CurrencyException $exception) {
                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($exception->getMessage());
                }

                $order->addError('paymentCurrency', $exception->getMessage());
                $session->setError($exception->getMessage());

                return null;
            }
        }

        $isSiteRequest = Craft::$app->getRequest()->getIsSiteRequest();

        // Allow setting the payment method at time of submitting payment.
        if ($gatewayId = $request->getParam('gatewayId')) {
            /** @var Gateway|null $gateway */
            $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

            if ($gateway && $gateway->availableForUseWithOrder($order)) {
                if ($isSiteRequest && $gateway->isFrontendEnabled) {
                    $order->setGatewayId($gatewayId);
                }
                if (!$isSiteRequest) {
                    $order->setGatewayId($gatewayId);
                }
            }
        }

        // This will get the gateway from the payment source first, and the current gateway second.
        $gateway = $order->getGateway();

        if ($gateway) {
            $gatewayAllowed = $gateway->availableForUseWithOrder($order);

            if ($isSiteRequest && !$gateway->isFrontendEnabled) {
                $gatewayAllowed = false;
            }

            if (!$gatewayAllowed) {
                $error = Plugin::t('Gateway is not available.');
                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($error);
                }

                $order->addError('gatewayId', $error);
                $session->setError($error);

                return null;
            }
        }

        /** @var Gateway $gateway */
        if (!$gateway) {
            $error = Plugin::t('There is no gateway or payment source selected for this order.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $session->setError($error);

            return null;
        }

        // Get the gateway's payment form
        $paymentForm = $gateway->getPaymentFormModel();
        $paymentForm->setAttributes($request->getBodyParams(), false);

        try {
            if ($request->getBodyParam('savePaymentSource') && $gateway->supportsPaymentSources() && $userId = $userSession->getId()) {
                $paymentSource = $plugin->getPaymentSources()->createPaymentSource($userId, $gateway, $paymentForm);
                try {
                    if ($userSession->getIsGuest() || !$paymentSource || $paymentSource->getUser()->id !== $userSession->getId()) {
                        throw new PaymentSourceException(Plugin::t('Cannot select payment source.'));
                    }
                    $order->gatewayId = null;
                    $order->paymentSourceId = $paymentSource->id;
                } catch (PaymentSourceException $exception) {
                    if ($request->getAcceptsJson()) {
                        return $this->asErrorJson($exception->getMessage());
                    }

                    $session->setError($exception->getMessage());

                    return null;
                }
            } else {
                $paymentSource = $order->getPaymentSource();
            }
        } catch (Exception $exception) {
            // Just attempt to pay by card, then.
            $paymentSource = null;
        }

        // If we have a payment source, populate from that as well.
        if ($paymentSource) {
            try {
                $paymentForm->populateFromPaymentSource($paymentSource);
            } catch (NotSupportedException $exception) {
                $customError = Plugin::t('Unable to make payment at this time.');

                if ($request->getAcceptsJson()) {
                    return $this->asJson(['error' => $customError, 'paymentFormErrors' => $paymentForm->getErrors(), 'orderErrors' => $order->getErrors()]);
                }

                $session->setError($customError);
                Craft::$app->getUrlManager()->setRouteParams(['paymentForm' => $paymentForm, $this->_cartVariableName => $order]);

                return null;
            }
        }

        // Allowed to update order's custom fields?
        if ($order->getIsActiveCart() || $userSession->checkPermission('commerce-manageOrders')) {
            $order->setFieldValuesFromRequest('fields');
        }

        // Check email address exists on order.
        if (!$order->email) {
            $customError = Plugin::t('No customer email address exists on this cart.');

            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $customError, 'paymentFormErrors' => $paymentForm->getErrors(), 'orderErrors' => $order->getErrors()]);
            }

            $session->setError($customError);
            Craft::$app->getUrlManager()->setRouteParams(['paymentForm' => $paymentForm, $this->_cartVariableName => $order]);

            return null;
        }

        // Does the order require shipping
        if ($plugin->getSettings()->requireShippingMethodSelectionAtCheckout && !$order->getShippingMethod()) {
            $customError = Plugin::t('There is no shipping method selected for this order.');

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

        if ($returnUrl !== null && $cancelUrl !== null) {
            $view = $this->getView();
            $order->returnUrl = $view->renderObjectTemplate($returnUrl, $order);
            $order->cancelUrl = $view->renderObjectTemplate($cancelUrl, $order);
        }

        // Do one final save to confirm the price does not change out from under the customer. Also removes any out of stock items etc.
        // This also confirms the products are available and discounts are current.
        $order->recalculate();
        // Save the orders new values.
        if (Craft::$app->getElements()->saveElement($order)) {
            $totalPriceChanged = $originalTotalPrice != $order->getOutstandingBalance();
            $totalQtyChanged = $originalTotalQty != $order->getTotalQty();
            $totalAdjustmentsChanged = $originalTotalAdjustments != count($order->getAdjustments());

            // Has the order changed in a significant way?
            if ($totalPriceChanged || $totalQtyChanged || $totalAdjustmentsChanged) {
                if ($totalPriceChanged) {
                    $order->addError('totalPrice', Plugin::t('The total price of the order changed.'));
                }

                if ($totalQtyChanged) {
                    $order->addError('totalQty', Plugin::t('The total quantity of items within the order changed.'));
                }

                if ($totalAdjustmentsChanged) {
                    $order->addError('totalAdjustments', Plugin::t('The total number of order adjustments changed.'));
                }

                $customError = Plugin::t('Something changed with the order before payment, please review your order and submit payment again.');

                if ($request->getAcceptsJson()) {
                    return $this->asJson(['error' => $customError, 'paymentFormErrors' => $paymentForm->getErrors(), 'orderErrors' => $order->getErrors()]);
                }

                $session->setError($customError);
                Craft::$app->getUrlManager()->setRouteParams(['paymentForm' => $paymentForm, $this->_cartVariableName => $order]);

                return null;
            }
        }

        $redirect = '';
        $transaction = null;
        $paymentForm->validate();

        if (!$paymentForm->hasErrors() && !$order->hasErrors()) {
            try {
                $plugin->getPayments()->processPayment($order, $paymentForm, $redirect, $transaction);
                $success = true;
            } catch (PaymentException $exception) {
                $customError = $exception->getMessage();
                $success = false;
            }
        } else {
            $customError = Plugin::t('Invalid payment or order. Please review.');
            $success = false;
        }

        if (!$success) {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $customError, 'paymentFormErrors' => $paymentForm->getErrors(), 'orderErrors' => $order->getErrors()]);
            }

            $session->setError($customError);

            Craft::$app->getUrlManager()->setRouteParams(['paymentForm' => $paymentForm, $this->_cartVariableName => $order]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            $response = ['success' => true, $this->_cartVariableName => $order->toArray()];

            if ($redirect) {
                $response['redirect'] = $redirect;
            }

            if ($transaction) {
                /** @var Transaction $transaction */
                $response['transactionId'] = $transaction->reference;
                $response['transactionHash'] = $transaction->hash;
            }

            return $this->asJson($response);
        }

        if ($redirect) {
            return $this->redirect($redirect);
        }

        if ($order->returnUrl) {
            return $this->redirect($order->returnUrl);
        } else {
            return $this->redirectToPostedUrl($order);
        }
    }

    /**
     * Processes return from off-site payment
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionCompletePayment(): Response
    {
        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();

        $hash = Craft::$app->getRequest()->getParam('commerceTransactionHash');

        $transaction = $plugin->getTransactions()->getTransactionByHash($hash);

        if (!$transaction) {
            throw new HttpException(400, Plugin::t('Can not complete payment for missing transaction.'));
        }

        $customError = '';
        $success = $plugin->getPayments()->completePayment($transaction, $customError);

        if ($success) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $response = ['url' => $transaction->order->returnUrl];

                return $this->asJson($response);
            }

            return $this->redirect($transaction->order->returnUrl);
        }

        Craft::$app->getSession()->setError(Plugin::t('Payment error: {message}', ['message' => $customError]));

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $response = ['url' => $transaction->order->cancelUrl];

            return $this->asJson($response);
        }

        return $this->redirect($transaction->order->cancelUrl);
    }
}
