<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use CraftCms\Commerce\Payment\Models\Transaction;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Transactions::class)` instead.
 */
class Transactions extends Component
{
    public const EVENT_AFTER_SAVE_TRANSACTION = \CraftCms\Commerce\Services\Transactions::EVENT_AFTER_SAVE_TRANSACTION;

    public const EVENT_AFTER_CREATE_TRANSACTION = \CraftCms\Commerce\Services\Transactions::EVENT_AFTER_CREATE_TRANSACTION;

    public function canCaptureTransaction(Transaction $transaction): bool
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->canCaptureTransaction($transaction);
    }

    public function canRefundTransaction(Transaction $transaction): bool
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->canRefundTransaction($transaction);
    }

    public function refundableAmountForTransaction(Transaction $transaction): float
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->refundableAmountForTransaction($transaction);
    }

    public function createTransaction(?Order $order = null, ?Transaction $parentTransaction = null, ?string $typeOverride = null): Transaction
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->createTransaction($order, $parentTransaction, $typeOverride);
    }

    public function deleteTransactionById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->deleteTransactionById($id);
    }

    /**
     * @return Transaction[]
     */
    public function getAllTopLevelTransactionsByOrderId(int $orderId): array
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getAllTopLevelTransactionsByOrderId($orderId);
    }

    /**
     * @return Transaction[]
     */
    public function getAllTransactionsByOrderId(int $orderId): array
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getAllTransactionsByOrderId($orderId);
    }

    /**
     * @return Transaction[]
     */
    public function getChildrenByTransactionId(int $transactionId): array
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getChildrenByTransactionId($transactionId);
    }

    public function getTransactionByHash(string $hash): ?Transaction
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getTransactionByHash($hash);
    }

    public function getTransactionByReferenceAndStatus(string $reference, string $status): ?Transaction
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getTransactionByReferenceAndStatus($reference, $status);
    }

    public function getTransactionByReference(string $reference): ?Transaction
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getTransactionByReference($reference);
    }

    public function getTransactionById(int $id): ?Transaction
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->getTransactionById($id);
    }

    public function isTransactionSuccessful(Transaction $transaction): bool
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->isTransactionSuccessful($transaction);
    }

    public function saveTransaction(Transaction $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->saveTransaction($model, $runValidation);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadTransactionsForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Services\Transactions::class)->eagerLoadTransactionsForOrders($orders);
    }
}
