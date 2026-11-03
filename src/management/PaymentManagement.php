<?php

declare(strict_types=1);

namespace craft\commerce\management;

use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\services\Payments;
use craft\commerce\services\Transactions;
use craft\elements\User;
use InvalidArgumentException;

readonly class PaymentManagement
{
    private Transactions $transactions;
    private Payments $payments;

    public function __construct(private OrderManagement $orders)
    {
        $this->transactions = Plugin::getInstance()->getTransactions();
        $this->payments = Plugin::getInstance()->getPayments();
    }

    public function capture(User $actor, int $transactionId): array
    {
        $transaction = $this->find($actor, $transactionId, 'capture');
        return Mutation::run("commerce:order-management:$transaction->orderId", function() use ($actor, $transactionId): array {
            $transaction = $this->find($actor, $transactionId, 'capture');
            if (!$transaction->canCapture()) {
                throw new InvalidArgumentException('This transaction cannot be captured.');
            }
            $result = $this->payments->captureTransaction($transaction);
            $result->getOrder()?->updateOrderPaidInformation();
            return ['transaction' => OrderManagement::transactionData($result)];
        });
    }

    public function refund(User $actor, int $transactionId, ?float $amount = null, string $note = ''): array
    {
        $transaction = $this->find($actor, $transactionId, 'refund');
        return Mutation::run("commerce:order-management:$transaction->orderId", function() use ($actor, $transactionId, $amount, $note): array {
            $transaction = $this->find($actor, $transactionId, 'refund');
            $remaining = $transaction->getRefundableAmount();
            $amount ??= $remaining;
            if (!$transaction->canRefund() || !is_finite($amount) || $amount <= 0 || $amount > $remaining) {
                throw new InvalidArgumentException('This transaction cannot be refunded for the requested amount.');
            }
            // Match Payments::_refund(), where a rounded zero would mean the entire remaining balance.
            $amount = Currency::round($amount, $transaction->currency);
            if ($amount <= 0 || $amount > $remaining) {
                throw new InvalidArgumentException('This transaction cannot be refunded for the requested amount.');
            }
            $result = $this->payments->refundTransaction($transaction, $amount, $note);
            $result->getOrder()?->updateOrderPaidInformation();
            return ['transaction' => OrderManagement::transactionData($result)];
        });
    }

    private function find(User $actor, int $id, string $ability): Transaction
    {
        $transaction = $this->transactions->getTransactionById($id);
        if (!$transaction || !$transaction->orderId) {
            throw new InvalidArgumentException('Resource not found.');
        }
        $this->orders->find($actor, $transaction->orderId, $ability);
        return $transaction;
    }
}
