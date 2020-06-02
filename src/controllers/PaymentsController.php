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
use craft\commerce\models\PaymentSource;
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
     * @throws NotSupportedException
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
        $isCpRequest = Craft::$app->getRequest()->getIsCpRequest();
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

        /**
         * Payments on completed orders can only be made if the order number and email
         * address are passed to the payments controller. If this is via the CP it
         * requires the user have the correct permission.
         */
        $checkPaymentCanBeMade = (($isSiteRequest && $order->getEmail() == $request->getParam('email')) || ($isCpRequest && $currentUser && $currentUser->can('commerce-manageOrders'))) && $number;
        if (!$order->getIsActiveCart() && !$checkPaymentCanBeMade) {
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

        if ($request->getBodyParam('registerUserOnOrderComplete') === 'false') {
            $order->registerUserOnOrderComplete = false;
        }

        // These are used to compare if the order changed during its final
        // recalculation before payment.
        $originalTotalPrice = $order->getOutstandingBalance();
        $originalTotalQty = $order->getTotalQty();
        $originalTotalAdjustments = count($order->getAdjustments());

        // Set guest email address onto guest customer and order.
        if ($paymentCurrency = $request->getParam('paymentCurrency')) {
            try {
                $order->setPaymentCurrency($paymentCurrency);
            } catch (CurrencyException $exception) {
                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($exception->getMessage());
                }

                $order->addError('paymentCurrency', $exception->getMessage());
                $session->setError($exception->getMessage());

                return null;
            }
        }

        // Set Payment Gateway on cart
        // Same as CartController::updateCart()
        if ($gatewayId = $request->getParam('gatewayId')) {
            if ($gateway = $plugin->getGateways()->getGatewayById($gatewayId)) {
                $order->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        // See CartController::updateCart()
        if ($paymentSourceId = $request->getParam('paymentSourceId')) {
            if ($paymentSource = $plugin->getPaymentSources()->getPaymentSourceById($paymentSourceId)) {
                // The payment source can only be used by the same user as the cart's user.
                $cartUserId = $order->getUser() ? $order->getUser()->id : null;
                $paymentSourceUserId = $paymentSource->getUser() ? $paymentSource->getUser()->id : null;
                $allowedToUsePaymentSource = ($cartUserId && $paymentSourceUserId && $currentUser && $isSiteRequest && ($paymentSourceUserId == $cartUserId));
                if ($allowedToUsePaymentSource) {
                    $order->setPaymentSource($paymentSource);
                }
            }
        }

        // This will return the gateway to be used. The orders gateway ID could be null, but it will know the gateway from the paymentSource ID
        $gateway = $order->getGateway();

        if (!$gateway || !$gateway->availableForUseWithOrder($order)) {
            $error = Plugin::t('There is no gateway or payment source available for this order.');

            if ($request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            if ($order->gatewayId) {
                $order->addError('gatewayId', $error);
            }

            if ($order->paymentSourceId) {
                $order->addError('paymentSourceId', $error);
            }

            $session->setError($error);

            return null;
        }

        // We need the payment form whether we are populating it from the request or from the payment source.
        $paymentForm = $gateway->getPaymentFormModel();

        /**
         *
         * Are we paying with:
         *
         * 1) The current order paymentSourceId
         * OR
         * 2) The current order gatewayId and a payment form populated from the request
         *
         */

        // 1) Paying with the current order paymentSourceId
        if ($order->paymentSourceId) {
            /** @var PaymentSource $paymentSource */
            $paymentSource = $order->getPaymentSource();
            if ($gateway->supportsPaymentSources()) {
                $paymentForm->populateFromPaymentSource($paymentSource);
            }
        }

        // 2) Paying with the current order gatewayId and a payment form populated from the request
        if ($order->gatewayId && !$order->paymentSourceId) {

            // Populate the payment form from the params
            $paymentForm->setAttributes($request->getBodyParams(), false);

            // Does the user want to save this card as a payment source?
            if ($currentUser && $request->getBodyParam('savePaymentSource') && $gateway->supportsPaymentSources()) {
                $paymentSource = $plugin->getPaymentSources()->createPaymentSource($currentUser->id, $gateway, $paymentForm);
                $order->setPaymentSource($paymentSource);
                $paymentForm->populateFromPaymentSource($paymentSource);
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

        $totalPriceChanged = $originalTotalPrice != $order->getOutstandingBalance();
        $totalQtyChanged = $originalTotalQty != $order->getTotalQty();
        $totalAdjustmentsChanged = $originalTotalAdjustments != count($order->getAdjustments());

        $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;
        $updateSearchIndex = ($order->isCompleted || $updateCartSearchIndexes);

        if (Craft::$app->getElements()->saveElement($order, true, false, $updateSearchIndex)) {
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

        // Make sure during this payment request the order does not recalculate.
        // We don't want to save the order in this mode in case the payment fails. The customer should still be able to edit and recalculate the cart.
        // When the order is marked as complete from a payment later, the order will be set to 'recalculate none' mode permanently.
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);

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
