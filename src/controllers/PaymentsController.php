<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\PaymentSourceCreatedLaterException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\helpers\Localization;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
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
    /**
     * @var string
     */
    private string $_cartVariableName;

    public function init(): void
    {
        parent::init();
        $this->_cartVariableName = Plugin::getInstance()->getSettings()->cartVariable;
    }

    /**
     * @inheritDoc
     */
    public function beforeAction($action): bool
    {
        // Don't enable CSRF validation for complete-payment requests since they can come from offsite, and the transaction hash is validated anyway.
        if ($action->id === 'complete-payment') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * @throws CurrencyException
     * @throws Exception
     * @throws NotSupportedException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionPay(): ?Response
    {
        $this->requirePostRequest();

        $error = '';

        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $isSiteRequest = $this->request->getIsSiteRequest();
        $isCpRequest = $this->request->getIsCpRequest();
        $userSession = Craft::$app->getUser();

        $number = $this->request->getParam('number');

        $useMutex = $number || (!$isCpRequest && $plugin->getCarts()->getHasSessionCartNumber());

        if ($useMutex) {
            $lockOrderNumber = null;
            if ($number) {
                $lockOrderNumber = $number;
            } elseif (!$isCpRequest) {
                $request = Craft::$app->getRequest();
                $requestCookies = $request->getCookies();
                $cookieNumber = $requestCookies->getValue($plugin->getCarts()->cartCookie['name']);

                if ($cookieNumber) {
                    $lockOrderNumber = $cookieNumber;
                }
            }

            if ($lockOrderNumber) {
                $lockName = "order:$lockOrderNumber";
                $mutex = Craft::$app->getMutex();
                if (!$mutex->acquire($lockName, 5)) {
                    throw new Exception('Unable to acquire a lock for saving of Order: ' . $lockOrderNumber);
                }
            }
        }


        if ($number !== null) {
            $order = $plugin->getOrders()->getOrderByNumber($number);

            if (!$order) {
                $error = Craft::t('commerce', 'Can not find an order to pay.');

                if ($this->request->getAcceptsJson()) {
                    return $this->asFailure($error, data: [
                        $this->_cartVariableName => null,
                    ]);
                }

                $this->setFailFlash($error);

                return null;
            }

            // TODO Fix this in Commerce 4. `order` if completed, `cartVariableName` if no completed. #COM-36
            $this->_cartVariableName = 'order'; // can not override the name of the order cart in json responses for orders
        } else {
            $order = $plugin->getCarts()->getCart();
        }

        /**
         * Payments on completed orders can only be made if the order number and email
         * address are passed to the payments controller. If this is via the control panel,
         * it requires the user have the correct permission.
         */
        $isSiteRequestAndAllowed = $isSiteRequest && $order->getEmail() == $this->request->getParam('email');
        $isCpAndAllowed = $isCpRequest && $currentUser && $currentUser->can('commerce-manageOrders');
        $checkPaymentCanBeMade = $number && ($isSiteRequestAndAllowed || $isCpAndAllowed);

        if (!$order->getIsActiveCart() && !$checkPaymentCanBeMade) {
            $error = Craft::t('commerce', 'Email required to make payments on a completed order.');
            return $this->asFailure($error, data: [
                $this->_cartVariableName => $this->cartArray($order),
            ]);
        }

        if ($order->getStore()->getRequireShippingAddressAtCheckout() && !$order->shippingAddressId) {
            $error = Craft::t('commerce', 'Shipping address required.');
            return $this->asFailure($error, data: [
                $this->_cartVariableName => $this->cartArray($order),
            ]);
        }

        if ($order->getStore()->getRequireBillingAddressAtCheckout() && !$order->billingAddressId) {
            $error = Craft::t('commerce', 'Billing address required.');
            return $this->asFailure($error, data: [
                $this->_cartVariableName => $this->cartArray($order),
            ]);
        }

        if (!$order->getStore()->getAllowEmptyCartOnCheckout() && $order->getIsEmpty()) {
            $error = Craft::t('commerce', 'Order can not be empty.');
            return $this->asFailure($error, data: [
                $this->_cartVariableName => $this->cartArray($order),
            ]);
        }

        // Set if the customer should be registered on order completion
        $registerUserOnOrderComplete = $this->request->getBodyParam('registerUserOnOrderComplete');
        if ($registerUserOnOrderComplete !== null) {
            $order->registerUserOnOrderComplete = (bool)$registerUserOnOrderComplete;
        }

        $saveBillingAddressOnOrderComplete = $this->request->getBodyParam('saveBillingAddressOnOrderComplete');
        if ($saveBillingAddressOnOrderComplete !== null) {
            $order->saveBillingAddressOnOrderComplete = (bool)$saveBillingAddressOnOrderComplete;
        }

        $saveShippingAddressOnOrderComplete = $this->request->getBodyParam('saveShippingAddressOnOrderComplete');
        if ($saveShippingAddressOnOrderComplete !== null) {
            $order->saveShippingAddressOnOrderComplete = (bool)$saveShippingAddressOnOrderComplete;
        }

        $saveAddressesOnOrderComplete = $this->request->getBodyParam('saveAddressesOnOrderComplete');
        if ($saveAddressesOnOrderComplete !== null) {
            $order->saveBillingAddressOnOrderComplete = (bool)$saveAddressesOnOrderComplete;
            $order->saveShippingAddressOnOrderComplete = (bool)$saveAddressesOnOrderComplete;
        }

        // These are used to compare if the order changed during its final
        // recalculation before payment.
        $originalTotalPrice = $order->getOutstandingBalance();
        $originalTotalQty = $order->getTotalQty();
        $originalTotalAdjustments = count($order->getAdjustments());

        // Set guest email address onto guest customer and order.
        if ($paymentCurrency = $this->request->getParam('paymentCurrency')) {
            try {
                $order->setPaymentCurrency($paymentCurrency);
            } catch (CurrencyException $exception) {
                Craft::$app->getErrorHandler()->logException($exception);

                $error = $exception->getMessage();
                $order->addError('paymentCurrency', $exception->getMessage());

                return $this->asFailure($error, data: [
                    $this->_cartVariableName => $this->cartArray($order),
                ]);
            }
        }

        // Set Payment Gateway on cart
        // Same as CartController::updateCart()
        if ($gatewayId = $this->request->getParam('gatewayId')) {
            if ($plugin->getGateways()->getGatewayById($gatewayId)) {
                $order->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        // See CartController::updateCart()
        if ($paymentSourceId = $this->request->getParam('paymentSourceId')) {
            if ($paymentSource = $plugin->getPaymentSources()->getPaymentSourceById($paymentSourceId)) {
                // The payment source can only be used by the same user as the cart's user.
                $cartUserId = $order->getCustomer()?->id;
                $paymentSourceUserId = $paymentSource->getCustomer()?->id;
                $allowedToUsePaymentSource = ($cartUserId && $paymentSourceUserId && $currentUser && $isSiteRequest && ($paymentSourceUserId == $cartUserId));
                if ($allowedToUsePaymentSource) {
                    $order->setPaymentSource($paymentSource);
                }
            }
        }

        // This will return the gateway to be used. The orders gateway ID could be null, but it will know the gateway from the paymentSource ID
        $gateway = $order->getGateway();

        if (!$gateway || !$gateway->availableForUseWithOrder($order) || (!$gateway->getIsFrontendEnabled() && !$isCpRequest)) {
            $error = Craft::t('commerce', 'There is no gateway or payment source available for use with this order.');

            if ($order->gatewayId) {
                $order->addError('gatewayId', $error);
            }

            if ($order->paymentSourceId) {
                $order->addError('paymentSourceId', $error);
            }

            return $this->asFailure($error, data: [
                $this->_cartVariableName => $this->cartArray($order),
            ]);
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
            $paymentFormParams = $this->request->getBodyParam(PaymentForm::getPaymentFormParamName($gateway->handle));

            if ($paymentFormParams) {
                $paymentForm->setAttributes($paymentFormParams, false);
            }

            // Does the user want to save this card as a payment source?
            if ($currentUser && $this->request->getBodyParam('savePaymentSource') && $gateway->supportsPaymentSources()) {
                $sourceCreated = false;
                try {
                    $paymentSource = $plugin->getPaymentSources()->createPaymentSource($currentUser->id, $gateway, $paymentForm);

                    // Last line of try block we have a successful payment source creation
                    $sourceCreated = true;
                } catch (PaymentSourceCreatedLaterException $exception) {
                    if (property_exists($paymentForm, 'paymentSource')) {
                        $paymentForm->savePaymentSource = true;
                    }
                } catch (PaymentSourceException $exception) {
                    Craft::$app->getErrorHandler()->logException($exception);

                    $error = $exception->getMessage();

                    return $this->asModelFailure(
                        $paymentForm,
                        $error,
                        'paymentForm',
                        [
                            $this->_cartVariableName => $this->cartArray($order),
                            'paymentFormErrors' => $paymentForm->getErrors(),
                        ],
                        [
                            $this->_cartVariableName => $order,
                        ]
                    );
                }

                if ($sourceCreated) {
                    /** @phpstan-ignore-next-line */
                    $order->setPaymentSource($paymentSource);
                    /** @phpstan-ignore-next-line */
                    $paymentForm->populateFromPaymentSource($paymentSource);
                }
            }
        }

        // Allowed to update order's custom fields?
        if ($order->getIsActiveCart() || $userSession->checkPermission('commerce-manageOrders')) {
            $order->setFieldValuesFromRequest('fields');
        }

        // Check email address exists on order.
        if (!$order->email) {
            $error = Craft::t('commerce', 'No customer email address exists on this cart.');

            return $this->asModelFailure(
                $paymentForm,
                $error,
                'paymentForm',
                [
                    $this->_cartVariableName => $this->cartArray($order),
                    'paymentFormErrors' => $paymentForm->getErrors(),
                ],
                [
                    $this->_cartVariableName => $order,
                ]
            );
        }

        // Does the order require shipping
        if ($order->hasShippableItems() && $order->getStore()->getRequireShippingMethodSelectionAtCheckout() && !$order->shippingMethodHandle) {
            $error = Craft::t('commerce', 'There is no shipping method selected for this order.');

            return $this->asModelFailure(
                $paymentForm,
                $error,
                'paymentForm',
                [
                    $this->_cartVariableName => $this->cartArray($order),
                ]
            );
        }

        // Save the return and cancel URLs to the order
        $returnUrl = $this->request->getValidatedBodyParam('redirect');
        if ($returnUrl !== null) {
            $order->returnUrl = $this->getView()->renderObjectTemplate($returnUrl, $order);
        }

        $cancelUrl = $this->request->getValidatedBodyParam('cancelUrl');
        if ($cancelUrl !== null) {
            $order->cancelUrl = $this->getView()->renderObjectTemplate($cancelUrl, $order);
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
                    $order->addError('totalPrice', Craft::t('commerce', 'The total price of the order changed.'));
                }

                if ($totalQtyChanged) {
                    $order->addError('totalQty', Craft::t('commerce', 'The total quantity of items within the order changed.'));
                }

                if ($totalAdjustmentsChanged) {
                    $order->addError('totalAdjustments', Craft::t('commerce', 'The total number of order adjustments changed.'));
                }

                $error = Craft::t('commerce', 'Something changed with the order before payment, please review your order and submit payment again.');

                if ($useMutex && isset($mutex, $lockName)) {
                    $mutex->release($lockName);
                }

                return $this->asModelFailure(
                    $paymentForm,
                    $error,
                    'paymentForm',
                    [
                        $this->_cartVariableName => $this->cartArray($order),
                        'paymentFormErrors' => $paymentForm->getErrors(),
                    ],
                    [
                        $this->_cartVariableName => $order,
                    ]
                );
            }
        }

        if ($useMutex && isset($mutex, $lockName)) {
            $mutex->release($lockName);
        }

        $redirect = '';
        $redirectData = [];
        $transaction = null;
        $paymentForm->validate();

        // Make sure during this payment request the order does not recalculate.
        // We don't want to save the order in this mode in case the payment fails. The customer should still be able to edit and recalculate the cart.
        // When the order is marked as complete from a payment later, the order will be set to 'recalculate none' mode permanently.
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);

        // set a partial payment amount on the order in the orders currency (not payment currency)
        $partialAllowed = (($this->request->isSiteRequest && $order->getStore()->getAllowPartialPaymentOnCheckout()) || $this->request->isCpRequest);

        if ($partialAllowed) {
            if ($isCpAndAllowed) {
                // Payment amount in the CP accepts number based in the user's formatting locale
                $cpPaymentAmount = $this->request->getBodyParam('paymentAmount');

                if (is_array($cpPaymentAmount)) {
                    $cpPaymentAmount = $cpPaymentAmount['value'];
                }
                $cpPaymentAmount = Localization::normalizeNumber($cpPaymentAmount);

                $order->setPaymentAmount($cpPaymentAmount);
            } elseif ($this->request->getBodyParam('paymentAmount')) {
                $paymentAmount = (float)$this->request->getValidatedBodyParam('paymentAmount');
                $order->setPaymentAmount($paymentAmount);
            }
        }

        if ((!$partialAllowed || !$gateway->supportsPartialPayment()) && $order->isPaymentAmountPartial()) {
            $error = Craft::t('commerce', 'Partial payment not allowed.');

            return $this->asModelFailure(
                $paymentForm,
                $error,
                'paymentForm',
                [
                    $this->_cartVariableName => $this->cartArray($order),
                    'paymentFormErrors' => $paymentForm->getErrors(),
                ],
                [
                    $this->_cartVariableName => $order,
                ]
            );
        }

        if (!$paymentForm->hasErrors() && !$order->hasErrors()) {
            try {
                $plugin->getPayments()->processPayment($order, $paymentForm, $redirect, $transaction, $redirectData);
                $success = true;
            } catch (PaymentException $exception) {
                $error = $exception->getMessage();
                $success = false;
            }
        } else {
            $error = Craft::t('commerce', 'Invalid payment or order. Please review.');
            $success = false;
        }

        if (!$success) {
            return $this->asModelFailure(
                $paymentForm,
                $error,
                'paymentForm',
                [
                    $this->_cartVariableName => $this->cartArray($order),
                    'paymentFormErrors' => $paymentForm->getErrors(),
                ],
                [
                    $this->_cartVariableName => $order,
                ]
            );
        }

        // If the gateway did not give us a redirect URL, use the order's return URL.
        if (!$redirect) {
            // Can be set from the redirect body param
            $redirect = $order->returnUrl;
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asModelSuccess(
                $paymentForm,
                $error,
                'paymentForm',
                [
                    $this->_cartVariableName => $this->cartArray($order),
                    'redirect' => $redirect,
                    'redirectData' => $redirectData,
                    'transactionId' => $transaction->reference ?? null,
                    'transactionHash' => $transaction->hash ?? null,
                ],
                $redirect
            );
        }

        return $this->redirect($redirect);
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

        $hash = $this->request->getParam('commerceTransactionHash');

        $transaction = $plugin->getTransactions()->getTransactionByHash($hash);

        if (!$transaction) {
            throw new HttpException(400, Craft::t('commerce', 'Can not complete payment for missing transaction.'));
        }

        $error = '';
        $success = $plugin->getPayments()->completePayment($transaction, $error);

        if (!$success) {
            $errorMessage = Craft::t('commerce', 'Payment error: {message}', ['message' => $error]);
            $this->setFailFlash($errorMessage);

            if ($this->request->getAcceptsJson()) {
                $data = [
                    'url' => $transaction->order->cancelUrl,
                ];

                return $this->asFailure($errorMessage, data: $data);
            }

            return $this->redirect($transaction->order->cancelUrl);
        }

        if ($this->request->getAcceptsJson()) {
            $data = [
                'url' => $transaction->order->returnUrl,
            ];

            return $this->asSuccess(data: $data);
        }

        return $this->redirect($transaction->order->returnUrl);
    }
}
