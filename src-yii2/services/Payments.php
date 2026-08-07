<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\RefundException;
use craft\commerce\errors\TransactionException;
use craft\commerce\models\payments\BasePaymentForm;
use CraftCms\Commerce\Payment\Models\Transaction;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Payments::class)` instead.
 */
class Payments extends Component
{
    public const EVENT_AFTER_COMPLETE_PAYMENT = \CraftCms\Commerce\Services\Payments::EVENT_AFTER_COMPLETE_PAYMENT;

    public const EVENT_BEFORE_CAPTURE_TRANSACTION = \CraftCms\Commerce\Services\Payments::EVENT_BEFORE_CAPTURE_TRANSACTION;

    public const EVENT_AFTER_CAPTURE_TRANSACTION = \CraftCms\Commerce\Services\Payments::EVENT_AFTER_CAPTURE_TRANSACTION;

    public const EVENT_BEFORE_REFUND_TRANSACTION = \CraftCms\Commerce\Services\Payments::EVENT_BEFORE_REFUND_TRANSACTION;

    public const EVENT_AFTER_REFUND_TRANSACTION = \CraftCms\Commerce\Services\Payments::EVENT_AFTER_REFUND_TRANSACTION;

    public const EVENT_BEFORE_PROCESS_PAYMENT = \CraftCms\Commerce\Services\Payments::EVENT_BEFORE_PROCESS_PAYMENT;

    public const EVENT_AFTER_PROCESS_PAYMENT = \CraftCms\Commerce\Services\Payments::EVENT_AFTER_PROCESS_PAYMENT;

    /**
     * @throws InvalidConfigException
     * @throws PaymentException if the payment was unsuccessful
     * @throws TransactionException
     */
    public function processPayment(Order $order, BasePaymentForm $form, ?string &$redirect, ?Transaction &$transaction, ?array &$redirectData = []): void
    {
        app(\CraftCms\Commerce\Services\Payments::class)->processPayment($order, $form, $redirect, $transaction, $redirectData);
    }

    /**
     * @throws TransactionException if something went wrong when saving the transaction
     */
    public function captureTransaction(Transaction $transaction): Transaction
    {
        return app(\CraftCms\Commerce\Services\Payments::class)->captureTransaction($transaction);
    }

    /**
     * @throws RefundException if something went wrong during the refund.
     */
    public function refundTransaction(Transaction $transaction, ?float $amount = null, string $note = ''): Transaction
    {
        return app(\CraftCms\Commerce\Services\Payments::class)->refundTransaction($transaction, $amount, $note);
    }

    public function completePayment(Transaction $transaction, ?string &$customError): bool
    {
        return app(\CraftCms\Commerce\Services\Payments::class)->completePayment($transaction, $customError);
    }
}
