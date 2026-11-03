<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Order\OrderManagement;
use CraftCms\Commerce\Payment\Data\Transaction;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

readonly class PaymentManagement
{
    public function __construct(private OrderManagement $orders, private Transactions $transactions, private Payments $payments)
    {
    }

    public function capture(CraftUser $actor, int $transactionId): array
    {
        $this->find($actor, $transactionId, 'capture');
        return Cache::lock("commerce:payment-management:$transactionId", 120)->block(5, function() use ($actor, $transactionId): array {
            $transaction = $this->find($actor, $transactionId, 'capture');
            if (!$transaction->canCapture()) {
                throw new InvalidArgumentException('This transaction cannot be captured.');
            }
            $result = $this->payments->captureTransaction($transaction);
            $result->getOrder()?->updateOrderPaidInformation();
            return ['transaction' => OrderManagement::transactionData($result)];
        });
    }

    public function refund(CraftUser $actor, int $transactionId, ?float $amount = null, string $note = ''): array
    {
        $this->find($actor, $transactionId, 'refund');
        return Cache::lock("commerce:payment-management:$transactionId", 120)->block(5, function() use ($actor, $transactionId, $amount, $note): array {
            $transaction = $this->find($actor, $transactionId, 'refund');
            $remaining = $transaction->getRefundableAmount();
            $amount ??= $remaining;
            if (!$transaction->canRefund() || !is_finite($amount) || $amount <= 0 || $amount > $remaining) {
                throw new InvalidArgumentException('This transaction cannot be refunded for the requested amount.');
            }
            $result = $this->payments->refundTransaction($transaction, $amount, $note);
            $result->getOrder()?->updateOrderPaidInformation();
            return ['transaction' => OrderManagement::transactionData($result)];
        });
    }

    private function find(CraftUser $actor, int $id, string $ability): Transaction
    {
        $transaction = $this->transactions->getTransactionById($id);
        if (!$transaction || !$transaction->orderId) {
            throw new InvalidArgumentException('Resource not found.');
        }
        $this->orders->find($actor, $transaction->orderId, $ability);
        return $transaction;
    }
}
