<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\RefundException;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\events\RefundTransactionEvent;
use craft\commerce\events\TransactionEvent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Settings;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use Exception;
use Throwable;
use yii\base\Component;

/**
 * Payments service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Payments extends Component
{
    /**
     * @event TransactionEvent The event that is triggered when a complete-payment request is made.
     * After this event, the customer will be redirected offsite or be redirected to the order success returnUrl.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Payments;
     * use craft\commerce\models\Transaction;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_AFTER_COMPLETE_PAYMENT,
     *     function(TransactionEvent $event) {
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *
     *         // Check whether it was an authorize transaction
     *         // and make sure that warehouse team is on top of it
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_COMPLETE_PAYMENT = 'afterCompletePayment';

    /**
     * @event TransactionEvent The event that is triggered before a payment transaction is captured.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Payments;
     * use craft\commerce\models\Transaction;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_BEFORE_CAPTURE_TRANSACTION,
     *     function(TransactionEvent $event) {
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *
     *         // Check that shipment’s ready before capturing
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_CAPTURE_TRANSACTION = 'beforeCaptureTransaction';

    /**
     * @event TransactionEvent The event that is triggered after a payment transaction is captured.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Payments;
     * use craft\commerce\models\Transaction;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_AFTER_CAPTURE_TRANSACTION,
     *     function(TransactionEvent $event) {
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *
     *         // Notify the warehouse we're ready to ship
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_CAPTURE_TRANSACTION = 'afterCaptureTransaction';

    /**
     * @event TransactionEvent The event that is triggered before a transaction is refunded.
     *
     * ```php
     * use craft\commerce\events\RefundTransactionEvent;
     * use craft\commerce\services\Payments;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_BEFORE_REFUND_TRANSACTION,
     *     function(RefundTransactionEvent $event) {
     *         // @var float $amount
     *         $amount = $event->amount;
     *
     *         // Do something else if the refund amount’s >50% of the transaction
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_REFUND_TRANSACTION = 'beforeRefundTransaction';

    /**
     * @event TransactionEvent The event that is triggered after a transaction is refunded.
     *
     * ```php
     * use craft\commerce\events\RefundTransactionEvent;
     * use craft\commerce\services\Payments;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_AFTER_REFUND_TRANSACTION,
     *     function(RefundTransactionEvent $event) {
     *         // @var float $amount
     *         $amount = $event->amount;
     *
     *         // Do something else if the refund amount’s >50% of the transaction
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_REFUND_TRANSACTION = 'afterRefundTransaction';

    /**
     * @event ProcessPaymentEvent The event that is triggered before a payment is processed.
     *
     * You may set the `isValid` property to `false` on the event to prevent the payment from being processed.
     *
     * ```php
     * use craft\commerce\events\ProcessPaymentEvent;
     * use craft\commerce\services\Payments;
     * use craft\commerce\elements\Order;
     * use craft\commerce\models\payments\BasePaymentForm;
     * use craft\commerce\models\Transaction;
     * use craft\commerce\base\RequestResponseInterface;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_BEFORE_PROCESS_PAYMENT,
     *     function(ProcessPaymentEvent $event) {
     *         // @var Order $order
     *         $order = $event->order;
     *         // @var BasePaymentForm $form
     *         $form = $event->form;
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *         // @var RequestResponseInterface $response
     *         $response = $event->response;
     *
     *         // Check some business rules to see whether the transaction is allowed
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_PROCESS_PAYMENT = 'beforeProcessPaymentEvent';

    /**
     * @event ProcessPaymentEvent The event that is triggered after a payment is processed.
     *
     * ```php
     * use craft\commerce\events\ProcessPaymentEvent;
     * use craft\commerce\services\Payments;
     * use craft\commerce\elements\Order;
     * use craft\commerce\models\payments\BasePaymentForm;
     * use craft\commerce\models\Transaction;
     * use craft\commerce\base\RequestResponseInterface;
     * use yii\base\Event;
     *
     * Event::on(
     *     Payments::class,
     *     Payments::EVENT_AFTER_PROCESS_PAYMENT,
     *     function(ProcessPaymentEvent $event) {
     *         // @var Order $order
     *         $order = $event->order;
     *         // @var BasePaymentForm $form
     *         $form = $event->form;
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *         // @var RequestResponseInterface $response
     *         $response = $event->response;
     *
     *         // Let the accounting department know an order transaction went through
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_PROCESS_PAYMENT = 'afterProcessPaymentEvent';

    /**
     * Process a payment.
     *
     * @param Order $order the order for which the payment is.
     * @param BasePaymentForm $form the payment form.
     * @param string|null &$redirect a string parameter by reference that will contain the redirect URL, if any
     * @param Transaction|null &$transaction the transaction
     * @return void|null
     * @throws PaymentException if the payment was unsuccessful
     * @throws Throwable if reasons
     */
    public function processPayment(Order $order, BasePaymentForm $form, &$redirect, &$transaction)
    {
        // Raise the 'beforeProcessPaymentEvent' event
        $event = new ProcessPaymentEvent(compact('order', 'form'));

        $this->trigger(self::EVENT_BEFORE_PROCESS_PAYMENT, $event);

        if (!$event->isValid) {
            // This error potentially is going to be displayed in the frontend, so we have to be vague about it.
            // Long story short - a plugin said "no."
            throw new PaymentException(Craft::t('commerce', 'Unable to make payment at this time.'));
        }

        // Order could have zero totalPrice and already considered 'paid'. Free orders complete immediately.
        $paymentStrategy = Plugin::getInstance()->getSettings()->freeOrderPaymentStrategy;
        if (!$order->hasOutstandingBalance() && !$order->datePaid && $paymentStrategy === Settings::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE) {
            $order->updateOrderPaidInformation();

            if ($order->isCompleted) {
                return;
            }
        }

        /** @var Gateway $gateway */
        $gateway = $order->getGateway();

        //choosing default action
        $defaultAction = $gateway->paymentType;
        $defaultAction = ($defaultAction === TransactionRecord::TYPE_PURCHASE) ? $defaultAction : TransactionRecord::TYPE_AUTHORIZE;

        if ($defaultAction === TransactionRecord::TYPE_AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                throw new PaymentException(Craft::t('commerce', 'Gateway doesn’t support authorize'));
            }
        } else if (!$gateway->supportsPurchase()) {
            throw new PaymentException(Craft::t('commerce', 'Gateway doesn’t support purchase'));
        }

        //creating order, transaction and request
        $transaction = Plugin::getInstance()->getTransactions()->createTransaction($order, null, $defaultAction);

        try {
            /** @var RequestResponseInterface $response */
            switch ($defaultAction) {
                case TransactionRecord::TYPE_PURCHASE:
                    $response = $gateway->purchase($transaction, $form);
                    break;
                case TransactionRecord::TYPE_AUTHORIZE:
                    $response = $gateway->authorize($transaction, $form);
                    break;
            }

            $this->_updateTransaction($transaction, $response);

            if ($this->hasEventHandlers(self::EVENT_AFTER_PROCESS_PAYMENT)) {
                $this->trigger(self::EVENT_AFTER_PROCESS_PAYMENT, new ProcessPaymentEvent(compact('order', 'transaction', 'form', 'response')));
            }

            // For redirects or unsuccessful transactions, save the transaction before bailing
            if ($response->isRedirect()) {
                $this->_handleRedirect($response, $redirect);
                return null;
            }

            if ($transaction->status !== TransactionRecord::STATUS_SUCCESS) {
                throw new PaymentException($transaction->message);
            }

            // Success!
            $order->updateOrderPaidInformation();
        } catch (Exception $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $transaction->message = $e->getMessage();

            // If this transactions is already saved, don't even try.
            if (!$transaction->id) {
                $this->_saveTransaction($transaction);
            }

            Craft::error($e->getMessage());
            throw new PaymentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Capture a transaction.
     *
     * @param Transaction $transaction the transaction to capture.
     * @return Transaction
     * @throws TransactionException if something went wrong when saving the transaction
     */
    public function captureTransaction(Transaction $transaction): Transaction
    {
        // Raise 'beforeCaptureTransaction' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_TRANSACTION)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        $transaction = $this->_capture($transaction);

        // Raise 'afterCaptureTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        return $transaction;
    }

    /**
     * Refund a transaction.
     *
     * @param Transaction $transaction the transaction to refund.
     * @param float|null $amount the amount to refund or null for full amount.
     * @param string $note the administrators note on the refund
     * @return Transaction
     * @throws RefundException if something went wrong during the refund.
     */
    public function refundTransaction(Transaction $transaction, $amount = null, $note = ''): Transaction
    {
        // Raise 'beforeRefundTransaction' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_REFUND_TRANSACTION)) {
            $this->trigger(self::EVENT_BEFORE_REFUND_TRANSACTION, new RefundTransactionEvent(compact('transaction', 'amount')));
        }

        $refundTransaction = $this->_refund($transaction, $amount, $note);

        /// Raise 'afterRefundTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFUND_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_REFUND_TRANSACTION, new RefundTransactionEvent(compact('transaction', 'refundTransaction', 'amount')));
        }

        return $refundTransaction;
    }

    /**
     * Process return from off-site payment.
     *
     * @param Transaction $transaction
     * @param string|null &$customError
     * @return bool
     */
    public function completePayment(Transaction $transaction, &$customError): bool
    {
        // Only transactions with the status of "redirect" can be completed
        if (!in_array($transaction->status, [TransactionRecord::STATUS_REDIRECT, TransactionRecord::STATUS_SUCCESS], true)) {
            $customError = $transaction->message;

            return false;
        }

        $transactionLockName = 'commerceTransaction:' . $transaction->hash;
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire($transactionLockName, 15)) {
            throw new \Exception('Unable to acquire a lock for transaction: ' . $transaction->hash);
        }

        // If it's successful already, we're good.
        if (Plugin::getInstance()->getTransactions()->isTransactionSuccessful($transaction)) {
            $transaction->order->updateOrderPaidInformation();
            $mutex->release($transactionLockName);
            return true;
        }

        // Load payment driver for the transaction we are trying to complete
        $gateway = $transaction->getGateway();

        switch ($transaction->type) {
            case TransactionRecord::TYPE_PURCHASE:
                $response = $gateway->completePurchase($transaction);
                break;
            case TransactionRecord::TYPE_AUTHORIZE:
                $response = $gateway->completeAuthorize($transaction);
                break;
            default:
                $mutex->release($transactionLockName);
                return false;
        }

        $childTransaction = Plugin::getInstance()->getTransactions()->createTransaction(null, $transaction);
        $this->_updateTransaction($childTransaction, $response);

        // Success can mean 2 things in this context.
        // 1) The transaction completed successfully with the gateway, and is now marked as complete.
        // 2) The result of the gateway request was successful but also got a redirect response. We now need to redirect if $redirect is not null.
        $success = $response->isSuccessful() || $response->isProcessing();
        $isParentTransactionRedirect = ($transaction->status === TransactionRecord::STATUS_REDIRECT);

        if ($success && ($transaction->status === TransactionRecord::STATUS_SUCCESS || ($isParentTransactionRedirect && $childTransaction->status == TransactionRecord::STATUS_SUCCESS))) {
            $transaction->order->updateOrderPaidInformation();
        }

        if ($success && ($transaction->status === TransactionRecord::STATUS_PROCESSING || ($isParentTransactionRedirect && $childTransaction->status == TransactionRecord::STATUS_PROCESSING))) {
            $transaction->order->markAsComplete();
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_PAYMENT)) {
            $this->trigger(self::EVENT_AFTER_COMPLETE_PAYMENT, new TransactionEvent([
                'transaction' => $transaction
            ]));
        }

        if ($response->isRedirect() && $transaction->status === TransactionRecord::STATUS_REDIRECT) {
            $mutex->release($transactionLockName);
            $this->_handleRedirect($response, $redirect);
            Craft::$app->getResponse()->redirect($redirect);
            Craft::$app->end();
        }

        if (!$success) {
            $customError = $response->getMessage();
        }

        $mutex->release($transactionLockName);

        return $success;
    }

    /**
     * Gets the total transactions amount really paid (not authorized).
     *
     * @param Order $order
     * @return float
     * @deprecated 3.1.11. Use getTotalPaid() instead.
     */
    public function getTotalPaidForOrder(Order $order): float
    {
        Craft::$app->getDeprecator()->log('Payments::getTotalPaidForOrder()', '`Payments::getTotalPaidForOrder()` has been deprecated. Use `Order::getTotalPaid()` instead.');

        return $order->getTotalPaid();
    }

    /**
     * Gets the total transactions amount refunded.
     *
     * @param Order $order
     * @return float
     * @deprecated in 3.1.11.
     */
    public function getTotalRefundedForOrder(Order $order): float
    {
        Craft::$app->getDeprecator()->log('Payments::getTotalRefundedForOrder()', '`Payments::getTotalRefundedForOrder()` has been deprecated.');

        // Since this method was only used by getTotalPaidForOrder, we don't need to move this to the order model since the logic is inside Order::getTotalPaid()
        $transactions = ArrayHelper::where($order->getTransactions(), static function(Transaction $transaction) {
            return ($transaction->status == TransactionRecord::STATUS_SUCCESS && $transaction->type == TransactionRecord::TYPE_REFUND);
        });

        return array_sum(ArrayHelper::getColumn($transactions, 'amount', false));
    }

    /**
     * Gets the total transactions amount with authorized.
     *
     * @param Order $order
     * @return float
     * @deprecated in 3.1.11.
     */
    public function getTotalAuthorizedOnlyForOrder(Order $order): float
    {
        Craft::$app->getDeprecator()->log('Payments::getTotalAuthorizedOnlyForOrder()', '`Payments::getTotalAuthorizedOnlyForOrder()` has been deprecated. Use `Order::getTotalAuthorized()` instead.');

        return $order->getTotalAuthorized();
    }

    /**
     *
     * @param Order $order
     * @return float
     * @deprecated in 3.1.11.
     */
    public function getTotalAuthorizedForOrder(Order $order): float
    {
        // At time of deprecation this method has no internal uses.
        Craft::$app->getDeprecator()->log('Payments::getTotalAuthorizedForOrder()', '`Payments::getTotalAuthorizedForOrder()` has been deprecated. It is not an accurate result since it does not take the amount paid from the authorized. Use `Order::getTotalAuthorized()` instead.');

        return (float)(new Query())
            ->from([Table::TRANSACTIONS])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_AUTHORIZE, TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]
            ])
            ->sum('amount');
    }


    /**
     * Handles a redirect.
     *
     * @param RequestResponseInterface $response
     * @param                          $redirect
     * @return mixed
     */
    private function _handleRedirect(RequestResponseInterface $response, &$redirect)
    {
        // If the gateway tells is it is a GET redirect, let them
        if ($response->getRedirectMethod() === 'GET') {
            $redirect = $response->getRedirectUrl();
        } else {
            $gatewayPostRedirectTemplate = Plugin::getInstance()->getSettings()->gatewayPostRedirectTemplate;

            if (!empty($gatewayPostRedirectTemplate)) {
                $variables = [];
                $hiddenFields = '';

                // Gather all post hidden data inputs.
                foreach ($response->getRedirectData() as $key => $value) {
                    $hiddenFields .= sprintf('<input type="hidden" name="%1$s" value="%2$s" />', htmlentities($key, ENT_QUOTES, 'UTF-8', false), htmlentities($value, ENT_QUOTES, 'UTF-8', false)) . "\n";
                }

                $variables['inputs'] = $hiddenFields;

                // Set the action url to the responses redirect url
                $variables['actionUrl'] = $response->getRedirectUrl();

                // Set Craft to the site template mode
                $templatesService = Craft::$app->getView();
                $oldTemplateMode = $templatesService->getTemplateMode();
                $templatesService->setTemplateMode($templatesService::TEMPLATE_MODE_SITE);

                $template = $templatesService->renderPageTemplate($gatewayPostRedirectTemplate, $variables);

                // Restore the original template mode
                $templatesService->setTemplateMode($oldTemplateMode);

                // Send the template back to the user.
                ob_start();
                echo $template;
                Craft::$app->end();
            }

            // Let the gateways response redirect us
            $response->redirect();
        }

        return null;
    }

    /**
     * Process a capture or refund exception.
     *
     * @param Transaction $parent
     * @return Transaction
     * @throws TransactionException if unable to save transaction
     */
    private function _capture(Transaction $parent): Transaction
    {
        $child = Plugin::getInstance()->getTransactions()->createTransaction(null, $parent, TransactionRecord::TYPE_CAPTURE);

        $gateway = $parent->getGateway();

        try {
            $response = $gateway->capture($child, (string)$parent->reference);
            $this->_updateTransaction($child, $response);
        } catch (Exception $e) {
            $child->status = TransactionRecord::STATUS_FAILED;
            $child->message = $e->getMessage();
            $this->_saveTransaction($child);

            Craft::error($e->getMessage());
        }

        return $child;
    }

    /**
     * Process a capture or refund exception.
     *
     * @param Transaction $parent
     * @param float|null $amount
     * @param string $note the administrators note on the refund
     * @return Transaction
     * @throws RefundException if anything goes wrong during a refund
     */
    private function _refund(Transaction $parent, float $amount = null, $note = ''): Transaction
    {
        try {
            $gateway = $parent->getGateway();

            if (!$gateway->supportsRefund()) {
                throw new SubscriptionException(Craft::t('commerce', 'Gateway doesn’t support refunds.'));
            }

            if ($amount < $parent->paymentAmount && !$gateway->supportsPartialRefund()) {
                throw new SubscriptionException(Craft::t('commerce', 'Gateway doesn’t support partial refunds.'));
            }

            $child = Plugin::getInstance()->getTransactions()->createTransaction(null, $parent, TransactionRecord::TYPE_REFUND);
            // If amount is not supplied refund the full amount
            $child->paymentAmount = $amount ?: $parent->getRefundableAmount();
            // Calculate amount in the primary currency
            $child->amount = $child->paymentAmount / $parent->paymentRate;
            $child->note = $note;

            $gateway = $parent->getGateway();

            try {
                $response = $gateway->refund($child);
                $this->_updateTransaction($child, $response);
            } catch (Throwable $exception) {
                Craft::error(Craft::t('commerce', 'Error refunding transaction: {transactionHash}', ['transactionHash' => $parent->hash]), 'commerce');
                $child->status = TransactionRecord::STATUS_FAILED;
                $child->message = $exception->getMessage();
                $this->_saveTransaction($child);
            }

            return $child;
        } catch (Throwable $exception) {
            throw new RefundException($exception->getMessage());
        }
    }

    /**
     * Save a transaction.
     *
     * @param Transaction $child
     * @throws TransactionException
     */
    private function _saveTransaction($child)
    {
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($child)) {
            throw new TransactionException('Error saving transaction: ' . implode(', ', $child->errors));
        }
    }

    /**
     * Updates a transaction.
     *
     * @param Transaction $transaction
     * @param RequestResponseInterface $response
     */
    private function _updateTransaction(Transaction $transaction, RequestResponseInterface $response)
    {
        if ($response->isSuccessful()) {
            $transaction->status = TransactionRecord::STATUS_SUCCESS;
        } elseif ($response->isProcessing()) {
            $transaction->status = TransactionRecord::STATUS_PROCESSING;
        } elseif ($response->isRedirect()) {
            $transaction->status = TransactionRecord::STATUS_REDIRECT;
        } else {
            $transaction->status = TransactionRecord::STATUS_FAILED;
        }

        $transaction->response = $response->getData();
        $transaction->code = $response->getCode();
        $transaction->reference = $response->getTransactionReference();
        $transaction->message = $response->getMessage();

        $this->_saveTransaction($transaction);
    }
}
