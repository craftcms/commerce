<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Commerce\Helpers\PaymentForm;
use CraftCms\Commerce\Http\Controllers\Concerns\HasCartArray;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Exceptions\CurrencyException;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Exceptions\PaymentSourceCreatedLaterException;
use CraftCms\Commerce\Payment\Exceptions\PaymentSourceException;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Payments;
use CraftCms\Commerce\Payment\PaymentSources;

use CraftCms\Commerce\Payment\Transactions;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

readonly class PaymentsController
{
    use HasCartArray;
    use RespondsWithFlash;

    public function pay(Request $request): ?Response
    {
        $plugin = Plugin::getInstance();
        $currentUser = currentUserElement();
        $isSiteRequest = !$request->isCpRequest();
        $isCpRequest = $request->isCpRequest();

        $number = $request->input('number');

        $useMutex = $number || (!$isCpRequest && app(Carts::class)->getHasSessionCartNumber());

        $mutex = null;

        if ($useMutex) {
            $lockOrderNumber = $number ?: (!$isCpRequest ? $request->cookie(app(Carts::class)->cartCookie['name']) : null);

            if ($lockOrderNumber) {
                $mutex = Cache::lock("order:$lockOrderNumber", 5);

                try {
                    $mutex->block(5);
                } catch (LockTimeoutException) {
                    abort(500, "Unable to acquire a lock for saving of Order: $lockOrderNumber");
                }
            }
        }

        $cartVariableName = $plugin->getSettings()->cartVariable;

        if ($number !== null) {
            $order = app(Orders::class)->getOrderByNumber($number);

            if (!$order) {
                $error = t('Can not find an order to pay.', category: 'commerce');

                if ($request->expectsJson()) {
                    return $this->asFailure($error, data: [
                        $cartVariableName => null,
                    ]);
                }

                return $this->asFailure($error);
            }

            // @TODO Fix the response variable name in Commerce 6.0: use `order` when completed and `cartVariableName` when not completed #COM-36
            $cartVariableName = 'order'; // can not override the name of the order cart in json responses for orders
        } else {
            $order = app(Carts::class)->getCart();
        }

        /**
         * Payments on completed orders can only be made if the order number and email
         * address are passed to the payments controller. If this is via the control panel,
         * it requires the user have the correct permission.
         */
        $isSiteRequestAndAllowed = $isSiteRequest && $order->getEmail() == $request->input('email');
        $isCpAndAllowed = $isCpRequest && $currentUser && $currentUser->can('commerce-manageOrders');
        $checkPaymentCanBeMade = $number && ($isSiteRequestAndAllowed || $isCpAndAllowed);

        if (!$order->getIsActiveCart() && !$checkPaymentCanBeMade) {
            return $this->asFailure(t('Email required to make payments on a completed order.', category: 'commerce'));
        }

        // Paying by order number + email is an anonymous flow: it doesn't prove the requester is
        // logged in as the order's own customer. If the order already has a payment source on file
        // (e.g. attached earlier by the credentialed customer), clear it unless the current user
        // actually is that customer, so it can't be charged anonymously.
        if ($number !== null && $isSiteRequestAndAllowed && $order->paymentSourceId) {
            $orderCustomer = $order->getCustomer();
            $isLoggedInAsOrderCustomer = $currentUser && $orderCustomer && $currentUser->id == $orderCustomer->id;
            if (!$isLoggedInAsOrderCustomer) {
                $order->setPaymentSource(null);
            }
        }

        if ($order->getStore()->getRequireShippingAddressAtCheckout() && !$order->shippingAddressId) {
            return $this->asFailure(t('Shipping address required.', category: 'commerce'), data: [
                $cartVariableName => $this->cartArray($order),
            ]);
        }

        if ($order->getStore()->getRequireBillingAddressAtCheckout() && !$order->billingAddressId) {
            return $this->asFailure(t('Billing address required.', category: 'commerce'), data: [
                $cartVariableName => $this->cartArray($order),
            ]);
        }

        if (!$order->getStore()->getAllowEmptyCartOnCheckout() && $order->getIsEmpty()) {
            return $this->asFailure(t('Order can not be empty.', category: 'commerce'), data: [
                $cartVariableName => $this->cartArray($order),
            ]);
        }

        // Set if the customer should be registered on order completion
        $registerUserOnOrderComplete = $request->input('registerUserOnOrderComplete');
        if ($registerUserOnOrderComplete !== null) {
            $order->registerUserOnOrderComplete = (bool)$registerUserOnOrderComplete;
        }

        $saveBillingAddressOnOrderComplete = $request->input('saveBillingAddressOnOrderComplete');
        if ($saveBillingAddressOnOrderComplete !== null) {
            $order->saveBillingAddressOnOrderComplete = (bool)$saveBillingAddressOnOrderComplete;
        }

        $saveShippingAddressOnOrderComplete = $request->input('saveShippingAddressOnOrderComplete');
        if ($saveShippingAddressOnOrderComplete !== null) {
            $order->saveShippingAddressOnOrderComplete = (bool)$saveShippingAddressOnOrderComplete;
        }

        $saveAddressesOnOrderComplete = $request->input('saveAddressesOnOrderComplete');
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
        if ($paymentCurrency = $request->input('paymentCurrency')) {
            try {
                $order->setPaymentCurrency($paymentCurrency);
            } catch (CurrencyException $exception) {
                Log::error($exception->getMessage(), ['exception' => $exception]);

                $order->addError('paymentCurrency', $exception->getMessage());

                return $this->asFailure($exception->getMessage(), data: [
                    $cartVariableName => $this->cartArray($order),
                ]);
            }
        }

        // Set Payment Gateway on cart
        // Same as CartController::updateCart()
        if ($gatewayId = $request->input('gatewayId')) {
            $gatewayId = (int)$gatewayId;
            if (app(Gateways::class)->getGatewayById($gatewayId)) {
                $order->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        // See CartController::updateCart()
        if ($paymentSourceId = $request->input('paymentSourceId')) {
            $paymentSourceId = (int)$paymentSourceId;
            if ($paymentSource = app(PaymentSources::class)->getPaymentSourceById($paymentSourceId)) {
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

        /** @phpstan-ignore-next-line method.notFound (getIsFrontendEnabled() is declared on legacy craft\commerce\base\GatewayTrait, which legacy craft\commerce\base\Gateway implements via the class_alias chain, which PHPStan can't trace) */
        if (!$gateway || !$gateway->availableForUseWithOrder($order) || (!$gateway->getIsFrontendEnabled() && !$isCpRequest)) {
            $error = t('There is no gateway or payment source available for use with this order.', category: 'commerce');

            if ($order->gatewayId) {
                $order->addError('gatewayId', $error);
            }

            if ($order->paymentSourceId) {
                $order->addError('paymentSourceId', $error);
            }

            return $this->asFailure($error, data: [
                $cartVariableName => $this->cartArray($order),
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
            /** @phpstan-ignore-next-line property.notFound ($handle is declared on legacy craft\commerce\base\GatewayTrait, which legacy craft\commerce\base\Gateway implements via the class_alias chain, which PHPStan can't trace) */
            $paymentFormParams = $request->input(PaymentForm::getPaymentFormParamName($gateway->handle));

            if ($paymentFormParams) {
                $paymentForm->setAttributes($paymentFormParams);
            }

            // Does the user want to save this card as a payment source?
            if ($currentUser && $request->input('savePaymentSource') && $gateway->supportsPaymentSources()) {
                $sourceCreated = false;
                try {
                    $paymentSource = app(PaymentSources::class)->createPaymentSource($currentUser->id, $gateway, $paymentForm);

                    // Last line of try block we have a successful payment source creation
                    $sourceCreated = true;
                } catch (PaymentSourceCreatedLaterException) {
                    if (property_exists($paymentForm, 'paymentSource')) {
                        $paymentForm->savePaymentSource = true;
                    }
                } catch (PaymentSourceException $exception) {
                    Log::error($exception->getMessage(), ['exception' => $exception]);

                    return $this->asModelFailure(
                        $paymentForm,
                        $exception->getMessage(),
                        'paymentForm',
                        [
                            $cartVariableName => $this->cartArray($order),
                            'paymentFormErrors' => $paymentForm->getErrors(),
                        ],
                    );
                }

                if ($sourceCreated) {
                    $order->setPaymentSource($paymentSource);
                    $paymentForm->populateFromPaymentSource($paymentSource);
                }
            }
        }

        // Allowed to update order's custom fields?
        if ($order->getIsActiveCart() || currentUser()?->can('commerce-manageOrders')) {
            $order->setFieldValuesFromRequest('fields');
        }

        // Check email address exists on order.
        if (!$order->email) {
            return $this->asModelFailure(
                $paymentForm,
                t('No customer email address exists on this cart.', category: 'commerce'),
                'paymentForm',
                [
                    $cartVariableName => $this->cartArray($order),
                    'paymentFormErrors' => $paymentForm->getErrors(),
                ],
            );
        }

        // Does the order require shipping
        if ($order->hasShippableItems() && $order->getStore()->getRequireShippingMethodSelectionAtCheckout() && !$order->shippingMethodHandle) {
            return $this->asModelFailure(
                $paymentForm,
                t('There is no shipping method selected for this order.', category: 'commerce'),
                'paymentForm',
                [
                    $cartVariableName => $this->cartArray($order),
                ],
            );
        }

        // Save the return and cancel URLs to the order
        $returnUrl = $request->input('redirect');
        if ($returnUrl !== null) {
            $order->returnUrl = renderSandboxedObjectTemplate($returnUrl, $order);
        }

        $cancelUrl = $request->input('cancelUrl');
        if ($cancelUrl !== null) {
            $order->cancelUrl = renderSandboxedObjectTemplate($cancelUrl, $order);
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

        if (Elements::saveElement($order, true, false, $updateSearchIndex)) {
            // Has the order changed in a significant way?
            if ($totalPriceChanged || $totalQtyChanged || $totalAdjustmentsChanged) {
                if ($totalPriceChanged) {
                    $order->addError('totalPrice', t('The total price of the order changed.', category: 'commerce'));
                }

                if ($totalQtyChanged) {
                    $order->addError('totalQty', t('The total quantity of items within the order changed.', category: 'commerce'));
                }

                if ($totalAdjustmentsChanged) {
                    $order->addError('totalAdjustments', t('The total number of order adjustments changed.', category: 'commerce'));
                }

                $mutex?->release();

                return $this->asModelFailure(
                    $paymentForm,
                    t('Something changed with the order before payment, please review your order and submit payment again.', category: 'commerce'),
                    'paymentForm',
                    [
                        $cartVariableName => $this->cartArray($order),
                        'paymentFormErrors' => $paymentForm->getErrors(),
                    ],
                );
            }
        }

        $mutex?->release();

        $redirect = '';
        $redirectData = [];
        $transaction = null;
        $paymentForm->validate();

        // Make sure during this payment request the order does not recalculate.
        // We don't want to save the order in this mode in case the payment fails. The customer should still be able to edit and recalculate the cart.
        // When the order is marked as complete from a payment later, the order will be set to 'recalculate none' mode permanently.
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);

        // set a partial payment amount on the order in the orders currency (not payment currency)
        $partialAllowed = ($isSiteRequest && $order->getStore()->getAllowPartialPaymentOnCheckout()) || $isCpRequest;

        if ($partialAllowed) {
            if ($isCpAndAllowed) {
                // Payment amount in the CP accepts number based in the user's formatting locale
                $cpPaymentAmount = $request->input('paymentAmount');

                if (is_array($cpPaymentAmount)) {
                    $cpPaymentAmount = $cpPaymentAmount['value'];
                }
                $cpPaymentAmount = (float)I18N::normalizeNumber($cpPaymentAmount);

                $order->setPaymentAmount($cpPaymentAmount);
            } elseif ($request->input('paymentAmount')) {
                $paymentAmount = (float)$request->input('paymentAmount');
                $order->setPaymentAmount($paymentAmount);
            }
        }

        if ((!$partialAllowed || !$gateway->supportsPartialPayment()) && $order->isPaymentAmountPartial()) {
            if (!$order->isCompleted) {
                $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            }

            return $this->asModelFailure(
                $paymentForm,
                t('Partial payment not allowed.', category: 'commerce'),
                'paymentForm',
                [
                    $cartVariableName => $this->cartArray($order),
                    'paymentFormErrors' => $paymentForm->getErrors(),
                ],
            );
        }

        $error = '';

        if (!$paymentForm->hasErrors() && !$order->hasErrors()) {
            try {
                app(Payments::class)->processPayment($order, $paymentForm, $redirect, $transaction, $redirectData);
                $success = true;
            } catch (PaymentException $exception) {
                $error = $exception->getMessage();
                $success = false;
            }
        } else {
            $error = t('Invalid payment or order. Please review.', category: 'commerce');
            $success = false;
        }

        if (!$success) {
            // Reset so the cart can still be edited and recalculated after a failed payment.
            if (!$order->isCompleted) {
                $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            }

            // Keep old paymentFormErrors as is.
            $originalPaymentFormErrors = $paymentForm->getErrors();

            // Adds the order errors to the payment form errors.
            $paymentForm->addModelErrors($order, $cartVariableName);

            return $this->asModelFailure(
                $paymentForm,
                $error,
                'paymentForm',
                [
                    $cartVariableName => $this->cartArray($order),
                    // @TODO Remove the legacy `paymentFormErrors` key in Commerce 6.0
                    'paymentFormErrors' => $originalPaymentFormErrors,
                ],
            );
        }

        // If the gateway did not give us a redirect URL, use the order's return URL.
        if (!$redirect) {
            // Can be set from the redirect body param
            $redirect = $order->returnUrl;
        }

        if ($request->expectsJson()) {
            return $this->asModelSuccess(
                $paymentForm,
                null,
                'paymentForm',
                [
                    $cartVariableName => $this->cartArray($order),
                    'redirect' => $redirect,
                    'redirectData' => $redirectData,
                    'transactionId' => $transaction->reference ?? null,
                    'transactionHash' => $transaction->hash ?? null,
                ],
                $redirect,
            );
        }

        return redirect($redirect);
    }

    public function completePayment(Request $request): Response
    {
        $hash = $request->input('commerceTransactionHash');
        abort_if(!$hash, 400, 'Missing commerceTransactionHash');

        $transaction = app(Transactions::class)->getTransactionByHash($hash);
        abort_unless($transaction !== null, 400, t('Can not complete payment for missing transaction.', category: 'commerce'));

        $error = '';
        $success = app(Payments::class)->completePayment($transaction, $error);

        if (!$success) {
            $errorMessage = t('Payment error: {message}', ['message' => $error], category: 'commerce');

            if ($request->expectsJson()) {
                return $this->asFailure($errorMessage, data: [
                    'url' => $transaction->order->cancelUrl,
                ]);
            }

            session()->flash('error', $errorMessage);

            return redirect($transaction->order->cancelUrl);
        }

        if ($request->expectsJson()) {
            return $this->asSuccess(data: [
                'url' => $transaction->order->returnUrl,
            ]);
        }

        return redirect($transaction->order->returnUrl);
    }
}
