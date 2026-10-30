<?php

namespace craft\commerce\services;

use craft\commerce\Plugin;
use CraftCms\Commerce\Payment\Events\PaymentCompleted;
use CraftCms\Commerce\Payment\Events\PaymentProcessed;
use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\Commerce\Payment\Events\TransactionCaptured;
use CraftCms\Commerce\Payment\Events\TransactionCapturing;
use CraftCms\Commerce\Payment\Events\TransactionRefunded;
use CraftCms\Commerce\Payment\Events\TransactionRefunding;
use Illuminate\Support\Facades\Event;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Exceptions\RefundException;
use CraftCms\Commerce\Payment\Exceptions\TransactionException;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Data\Transaction;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Payment\Payments::class)` instead.
 */
class Payments extends Component
{
    public const EVENT_AFTER_COMPLETE_PAYMENT = \CraftCms\Commerce\Payment\Payments::EVENT_AFTER_COMPLETE_PAYMENT;

    public const EVENT_BEFORE_CAPTURE_TRANSACTION = \CraftCms\Commerce\Payment\Payments::EVENT_BEFORE_CAPTURE_TRANSACTION;

    public const EVENT_AFTER_CAPTURE_TRANSACTION = \CraftCms\Commerce\Payment\Payments::EVENT_AFTER_CAPTURE_TRANSACTION;

    public const EVENT_BEFORE_REFUND_TRANSACTION = \CraftCms\Commerce\Payment\Payments::EVENT_BEFORE_REFUND_TRANSACTION;

    public const EVENT_AFTER_REFUND_TRANSACTION = \CraftCms\Commerce\Payment\Payments::EVENT_AFTER_REFUND_TRANSACTION;

    public const EVENT_BEFORE_PROCESS_PAYMENT = \CraftCms\Commerce\Payment\Payments::EVENT_BEFORE_PROCESS_PAYMENT;

    public const EVENT_AFTER_PROCESS_PAYMENT = \CraftCms\Commerce\Payment\Payments::EVENT_AFTER_PROCESS_PAYMENT;

    /**
     * @throws InvalidConfigException
     * @throws PaymentException if the payment was unsuccessful
     * @throws TransactionException
     */
    public function processPayment(Order $order, BasePaymentForm $form, ?string &$redirect, ?Transaction &$transaction, ?array &$redirectData = []): void
    {
        app(\CraftCms\Commerce\Payment\Payments::class)->processPayment($order, $form, $redirect, $transaction, $redirectData);
    }

    /**
     * @throws TransactionException if something went wrong when saving the transaction
     */
    public function captureTransaction(Transaction $transaction): Transaction
    {
        return app(\CraftCms\Commerce\Payment\Payments::class)->captureTransaction($transaction);
    }

    /**
     * @throws RefundException if something went wrong during the refund.
     */
    public function refundTransaction(Transaction $transaction, ?float $amount = null, string $note = ''): Transaction
    {
        return app(\CraftCms\Commerce\Payment\Payments::class)->refundTransaction($transaction, $amount, $note);
    }

    public function completePayment(Transaction $transaction, ?string &$customError): bool
    {
        return app(\CraftCms\Commerce\Payment\Payments::class)->completePayment($transaction, $customError);
    }

    public static function registerEvents(): void
    {
        Event::listen(PaymentProcessing::class, static function(PaymentProcessing $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_PROCESS_PAYMENT)) {
                $legacy->trigger(self::EVENT_BEFORE_PROCESS_PAYMENT, $event);
            }
        });

        Event::listen(PaymentProcessed::class, static function(PaymentProcessed $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_PROCESS_PAYMENT)) {
                $legacy->trigger(self::EVENT_AFTER_PROCESS_PAYMENT, $event);
            }
        });

        Event::listen(TransactionCapturing::class, static function(TransactionCapturing $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_TRANSACTION)) {
                $legacy->trigger(self::EVENT_BEFORE_CAPTURE_TRANSACTION, $event);
            }
        });

        Event::listen(TransactionCaptured::class, static function(TransactionCaptured $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_CAPTURE_TRANSACTION)) {
                $legacy->trigger(self::EVENT_AFTER_CAPTURE_TRANSACTION, $event);
            }
        });

        Event::listen(TransactionRefunding::class, static function(TransactionRefunding $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_REFUND_TRANSACTION)) {
                $legacy->trigger(self::EVENT_BEFORE_REFUND_TRANSACTION, $event);
            }
        });

        Event::listen(TransactionRefunded::class, static function(TransactionRefunded $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_REFUND_TRANSACTION)) {
                $legacy->trigger(self::EVENT_AFTER_REFUND_TRANSACTION, $event);
            }
        });

        Event::listen(PaymentCompleted::class, static function(PaymentCompleted $event) {
            $legacy = Plugin::getInstance()->getPayments();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_COMPLETE_PAYMENT)) {
                $legacy->trigger(self::EVENT_AFTER_COMPLETE_PAYMENT, $event);
            }
        });
    }
}
