<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Exceptions\ManualTransactionException;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use Illuminate\Container\Attributes\Singleton;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Records payments and refunds that happened outside Commerce, such as cash, a bank transfer, or a payment taken in a gateway’s own virtual terminal.
 *
 * Rows are ordinary `purchase` and `refund` transactions flagged as manual, so totals and paid status work as they do for
 * gateway transactions. They are always in the order’s currency and are never edited: a mistake is corrected with a compensating refund.
 *
 * @since 6.0.0
 */
#[Singleton]
class ManualTransactions
{
    public function __construct(private readonly Transactions $transactions)
    {
    }

    /**
     * @throws ManualTransactionException
     */
    public function recordPayment(Order $order, float $amount, ?string $reference = null, ?string $note = null): Transaction
    {
        $this->assertRecordable($order, $amount);

        return $this->save($order, TransactionRecord::TYPE_PURCHASE, $amount, $reference, $note);
    }

    /**
     * @throws ManualTransactionException
     */
    public function recordRefund(Order $order, float $amount, ?string $reference = null, ?string $note = null, ?Transaction $parent = null): Transaction
    {
        $this->assertRecordable($order, $amount);

        if ($amount > $this->maximumRefund($order, $parent)) {
            throw new ManualTransactionException(t('Can not refund more than {amount}.', ['amount' => Currency::formatAsCurrency($this->maximumRefund($order, $parent), $order->currency)], category: 'commerce'));
        }

        return $this->save($order, TransactionRecord::TYPE_REFUND, $amount, $reference, $note, $parent);
    }

    /**
     * The most a manual refund can be: what the order has been paid, and, when it is linked to a payment, what is left of that payment.
     */
    public function maximumRefund(Order $order, ?Transaction $parent = null): float
    {
        $maximum = max(0.0, $order->getTotalPaid());

        if ($parent === null) {
            return $maximum;
        }

        $rate = $parent->paymentRate ?: 1.0;

        return min($maximum, Currency::round($parent->getRefundableAmount() / $rate));
    }

    /**
     * @throws ManualTransactionException
     */
    private function assertRecordable(Order $order, float $amount): void
    {
        if (!$order->id || !$order->isCompleted) {
            throw new ManualTransactionException(t('Transactions can only be recorded on completed orders.', category: 'commerce'));
        }

        if ($amount <= 0) {
            throw new ManualTransactionException(t('The amount must be greater than zero.', category: 'commerce'));
        }
    }

    /**
     * @throws ManualTransactionException
     */
    private function save(Order $order, string $type, float $amount, ?string $reference, ?string $note, ?Transaction $parent = null): Transaction
    {
        $amount = Currency::round($amount);

        $transaction = new Transaction([
            'orderId' => $order->id,
            'parentId' => $parent?->id,
            'userId' => currentUserElement()?->id,
            'gatewayId' => null,
            'type' => $type,
            'status' => TransactionRecord::STATUS_SUCCESS,
            'currency' => $order->currency,
            'paymentCurrency' => $order->currency,
            'paymentRate' => 1.0,
            'amount' => $amount,
            'paymentAmount' => $amount,
            'reference' => $reference ?: null,
            'note' => (string)$note,
        ]);
        $transaction->setOrder($order);

        if (!$this->transactions->saveTransaction($transaction)) {
            throw new ManualTransactionException(t('Couldn’t record the transaction.', category: 'commerce'));
        }

        return $transaction;
    }
}
