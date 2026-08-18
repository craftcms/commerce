<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Payment\Events\ProcessPaymentEvent;
use CraftCms\Commerce\Payment\Events\RefundTransactionEvent;
use CraftCms\Commerce\Payment\Events\TransactionEvent;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Exceptions\RefundException;
use CraftCms\Commerce\Payment\Exceptions\TransactionException;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Models\Transaction;
use CraftCms\Commerce\Payment\Records\Transaction as TransactionRecord;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\Cms\Support\Facades\Template;
use CraftCms\Cms\View\TemplateMode;
use Exception;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ExitException;
use function CraftCms\Cms\t;

#[Singleton]
class Payments
{
    public const string EVENT_AFTER_COMPLETE_PAYMENT = 'afterCompletePayment';

    public const string EVENT_BEFORE_CAPTURE_TRANSACTION = 'beforeCaptureTransaction';

    public const string EVENT_AFTER_CAPTURE_TRANSACTION = 'afterCaptureTransaction';

    public const string EVENT_BEFORE_REFUND_TRANSACTION = 'beforeRefundTransaction';

    public const string EVENT_AFTER_REFUND_TRANSACTION = 'afterRefundTransaction';

    public const string EVENT_BEFORE_PROCESS_PAYMENT = 'beforeProcessPaymentEvent';

    public const string EVENT_AFTER_PROCESS_PAYMENT = 'afterProcessPaymentEvent';

    /**
     * Process a payment.
     *
     * @param string|null $redirect a string parameter by reference that will contain the redirect URL, if any
     * @param Transaction|null $transaction the transaction
     * @param array|null $redirectData the additional data the gateway might need to redirect the user to the payment page. This is useful for ajax payment responses.
     * @throws PaymentException if the payment was unsuccessful
     */
    public function processPayment(Order $order, BasePaymentForm $form, ?string &$redirect, ?Transaction &$transaction, ?array &$redirectData = []): void
    {
        // Raise the 'beforeProcessPaymentEvent' event
        $event = new ProcessPaymentEvent(order: $order, form: $form);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getPayments()->trigger(self::EVENT_BEFORE_PROCESS_PAYMENT, $event);

        if (!$event->isValid) {
            // This error potentially is going to be displayed in the frontend, so we have to be vague about it.
            // Long story short - a plugin said "no."
            throw new PaymentException(t('Unable to make payment at this time.', category: 'commerce'));
        }

        // Order could have zero totalPrice and already considered 'paid'. Free orders complete immediately.
        $paymentStrategy = $order->getStore()->getFreeOrderPaymentStrategy();
        if (!$order->hasOutstandingBalance() && !$order->datePaid && $paymentStrategy === Store::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE) {
            $order->updateOrderPaidInformation();

            if ($order->isCompleted) {
                return;
            }
        }

        $gateway = $order->getGateway();
        if (!$gateway) {
            throw new \RuntimeException(t('Missing Gateway', category: 'commerce'));
        }

        //choosing default action
        $defaultAction = $gateway->paymentType;
        $defaultAction = ($defaultAction === TransactionRecord::TYPE_PURCHASE) ? $defaultAction : TransactionRecord::TYPE_AUTHORIZE;

        if ($defaultAction === TransactionRecord::TYPE_AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                throw new PaymentException(t('Gateway doesn\'t support authorize', category: 'commerce'));
            }
        } elseif (!$gateway->supportsPurchase()) {
            throw new PaymentException(t('Gateway doesn\'t support purchase', category: 'commerce'));
        }

        //creating order, transaction and request
        $transaction = app(Transactions::class)->createTransaction($order, null, $defaultAction);

        try {
            $response = match ($defaultAction) {
                TransactionRecord::TYPE_PURCHASE => $gateway->purchase($transaction, $form),
                TransactionRecord::TYPE_AUTHORIZE => $gateway->authorize($transaction, $form),
            };

            $this->updateTransaction($transaction, $response);

            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getPayments()->hasEventHandlers(self::EVENT_AFTER_PROCESS_PAYMENT)) {
                $afterEvent = new ProcessPaymentEvent(order: $order, form: $form);
                $afterEvent->transaction = $transaction;
                $afterEvent->response = $response;
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getPayments()->trigger(self::EVENT_AFTER_PROCESS_PAYMENT, $afterEvent);
            }

            // For redirects or unsuccessful transactions, save the transaction before bailing
            if ($response->isRedirect()) {
                $this->handleRedirect($response, $redirect, $redirectData);
                return;
            }

            if (!in_array($transaction->status, [TransactionRecord::STATUS_SUCCESS, TransactionRecord::STATUS_PROCESSING])) {
                throw new PaymentException($transaction->message);
            }

            // Success!
            $order->updateOrderPaidInformation();
        } catch (Exception $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $transaction->message = $e->getMessage();

            // If this transactions is already saved, don't even try.
            if (!$transaction->id) {
                $this->saveTransaction($transaction);
            }

            Log::error($e->getMessage(), ['exception' => $e]);
            throw new PaymentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Capture a transaction.
     *
     * @throws TransactionException if something went wrong when saving the transaction
     */
    public function captureTransaction(Transaction $transaction): Transaction
    {
        // Raise 'beforeCaptureTransaction' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPayments()->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_TRANSACTION)) {
            $beforeEvent = new TransactionEvent(transaction: $transaction);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPayments()->trigger(self::EVENT_BEFORE_CAPTURE_TRANSACTION, $beforeEvent);
        }

        $transaction = $this->capture($transaction);

        // Raise 'afterCaptureTransaction' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPayments()->hasEventHandlers(self::EVENT_AFTER_CAPTURE_TRANSACTION)) {
            $afterEvent = new TransactionEvent(transaction: $transaction);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPayments()->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, $afterEvent);
        }

        return $transaction;
    }

    /**
     * Refund a transaction.
     *
     * @param float|null $amount the amount to refund or null for full amount.
     * @param string $note the administrators note on the refund
     * @throws RefundException if something went wrong during the refund.
     */
    public function refundTransaction(Transaction $transaction, ?float $amount = null, string $note = ''): Transaction
    {
        // Raise 'beforeRefundTransaction' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPayments()->hasEventHandlers(self::EVENT_BEFORE_REFUND_TRANSACTION)) {
            $beforeEvent = new RefundTransactionEvent(transaction: $transaction, amount: $amount);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPayments()->trigger(self::EVENT_BEFORE_REFUND_TRANSACTION, $beforeEvent);
        }

        $refundTransaction = $this->refund($transaction, $amount, $note);

        // Raise 'afterRefundTransaction' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPayments()->hasEventHandlers(self::EVENT_AFTER_REFUND_TRANSACTION)) {
            $afterEvent = new RefundTransactionEvent(transaction: $transaction, amount: $amount);
            $afterEvent->refundTransaction = $refundTransaction;
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPayments()->trigger(self::EVENT_AFTER_REFUND_TRANSACTION, $afterEvent);
        }

        return $refundTransaction;
    }

    /**
     * Process return from off-site payment.
     *
     * @throws Throwable
     * @throws \craft\commerce\errors\OrderStatusException
     * @throws \CraftCms\Cms\Element\Queries\Exceptions\ElementNotFoundException
     */
    public function completePayment(Transaction $transaction, ?string &$customError): bool
    {
        // Only transactions with the status of "redirect" can be completed
        if (!in_array($transaction->status, [TransactionRecord::STATUS_REDIRECT, TransactionRecord::STATUS_SUCCESS], true)) {
            $customError = $transaction->message;

            return false;
        }

        $transactionLockName = 'commerceTransaction:' . $transaction->hash;
        $lock = Cache::lock($transactionLockName, 60);
        try {
            $lock->block(15);
        } catch (LockTimeoutException) {
            throw new Exception('Unable to acquire a lock for transaction: ' . $transaction->hash);
        }

        // Make sure we have the latest transaction data
        $transaction = app(Transactions::class)->getTransactionByHash($transaction->hash);

        // If it's successful already, we're good.
        if (app(Transactions::class)->isTransactionSuccessful($transaction)) {
            $transaction->getOrder()->updateOrderPaidInformation();
            $lock->release();
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
                $lock->release();
                return false;
        }

        $childTransaction = app(Transactions::class)->createTransaction(null, $transaction);
        $this->updateTransaction($childTransaction, $response);

        // Success can mean 2 things in this context.
        // 1) The transaction completed successfully with the gateway, and is now marked as complete.
        // 2) The result of the gateway request was successful but also got a redirect response. We now need to redirect if $redirect is not null.
        $success = $response->isSuccessful() || $response->isProcessing();
        $isParentTransactionRedirect = ($transaction->status === TransactionRecord::STATUS_REDIRECT);

        if ($success) {
            if ($transaction->status === TransactionRecord::STATUS_SUCCESS || ($isParentTransactionRedirect && $childTransaction->status == TransactionRecord::STATUS_SUCCESS)) {
                $transaction->getOrder()->updateOrderPaidInformation();
            }

            if ($isParentTransactionRedirect && $childTransaction->status == TransactionRecord::STATUS_PROCESSING) {
                $transaction->getOrder()->markAsComplete();
            }
        }

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPayments()->hasEventHandlers(self::EVENT_AFTER_COMPLETE_PAYMENT)) {
            $completeEvent = new TransactionEvent(transaction: $transaction);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPayments()->trigger(self::EVENT_AFTER_COMPLETE_PAYMENT, $completeEvent);
        }

        $redirectData = [];
        if ($response->isRedirect() && $transaction->status === TransactionRecord::STATUS_REDIRECT) {
            $lock->release();
            $this->handleRedirect($response, $redirect, $redirectData);
            \Craft::$app->getResponse()->redirect($redirect);
            \Craft::$app->end();
        }

        if (!$success) {
            $customError = $response->getMessage();
        }

        $lock->release();

        return $success;
    }

    /**
     * Handles a redirect.
     *
     * @throws ExitException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \Exception
     */
    private function handleRedirect(RequestResponseInterface $response, ?string &$redirect, ?array &$redirectData): void
    {
        // If the gateway tells is it is a GET redirect, let them
        if ($response->getRedirectMethod() === 'GET') {
            $redirect = $response->getRedirectUrl();
            $redirectData = $response->getRedirectData();
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

                $template = Template::renderPageTemplate($gatewayPostRedirectTemplate, $variables, TemplateMode::Site);

                // Send the template back to the user.
                ob_start();
                echo $template;
                \Craft::$app->end();
            }

            // Let the gateway's response redirect us
            $response->redirect();
        }
    }

    /**
     * Process a capture or refund exception.
     *
     * @throws TransactionException if unable to save transaction
     */
    private function capture(Transaction $parent): Transaction
    {
        $child = app(Transactions::class)->createTransaction(null, $parent, TransactionRecord::TYPE_CAPTURE);

        $gateway = $parent->getGateway();

        try {
            $response = $gateway->capture($child, (string)$parent->reference);
            $this->updateTransaction($child, $response);
        } catch (Exception $e) {
            $child->status = TransactionRecord::STATUS_FAILED;
            $child->message = $e->getMessage();
            $this->saveTransaction($child);

            Log::error($e->getMessage(), ['exception' => $e]);
        }

        return $child;
    }

    /**
     * Process a capture or refund exception.
     *
     * @param string $note the administrators note on the refund
     * @throws RefundException if anything goes wrong during a refund
     */
    private function refund(Transaction $parent, ?float $amount = null, string $note = ''): Transaction
    {
        try {
            $gateway = $parent->getGateway();

            if (!$gateway->supportsRefund()) {
                throw new RefundException(t('Gateway doesn\'t support refunds.', category: 'commerce'));
            }

            if ($amount < $parent->paymentAmount && !$gateway->supportsPartialRefund()) {
                throw new RefundException(t('Gateway doesn\'t support partial refunds.', category: 'commerce'));
            }

            $child = app(Transactions::class)->createTransaction(null, $parent, TransactionRecord::TYPE_REFUND);

            // If amount is not supplied refund the full amount
            $child->paymentAmount = Currency::round($amount, $child->currency) ?: $parent->getRefundableAmount();

            // Calculate amount in the primary currency
            $child->amount = Currency::round($child->paymentAmount / $parent->paymentRate, $child->currency);
            $child->note = $note;

            $gateway = $parent->getGateway();

            try {
                $response = $gateway->refund($child);
                $this->updateTransaction($child, $response);
            } catch (Throwable $exception) {
                Log::error(t('Error refunding transaction: {transactionHash}', ['transactionHash' => $parent->hash], category: 'commerce'));
                $child->status = TransactionRecord::STATUS_FAILED;
                $child->message = $exception->getMessage();
                $this->saveTransaction($child);
            }

            return $child;
        } catch (Throwable $exception) {
            throw new RefundException($exception->getMessage());
        }
    }

    /**
     * Save a transaction.
     *
     * @throws TransactionException
     */
    private function saveTransaction(Transaction $child): void
    {
        if (!app(Transactions::class)->saveTransaction($child)) {
            throw new TransactionException('Error saving transaction: ' . implode(', ', $child->getFirstErrors()));
        }
    }

    /**
     * Updates a transaction.
     */
    private function updateTransaction(Transaction $transaction, RequestResponseInterface $response): void
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

        $this->saveTransaction($transaction);
    }
}
