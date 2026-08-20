<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use craft\commerce\base\Gateway;
use craft\commerce\Plugin;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Events\TransactionEvent;
use CraftCms\Commerce\Payment\Exceptions\TransactionException;
use CraftCms\Commerce\Payment\Models\Transaction;
use CraftCms\Commerce\Payment\Records\Transaction as TransactionRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function CraftCms\Cms\currentUserElement;

#[Singleton]
class Transactions
{
    public const string EVENT_AFTER_SAVE_TRANSACTION = 'afterSaveTransaction';

    public const string EVENT_AFTER_CREATE_TRANSACTION = 'afterCreateTransaction';

    /**
     * Returns true if a specific transaction can be captured.
     */
    public function canCaptureTransaction(Transaction $transaction): bool
    {
        // Can only capture successful authorize transactions
        if ($transaction->type !== TransactionRecord::TYPE_AUTHORIZE || $transaction->status !== TransactionRecord::STATUS_SUCCESS) {
            return false;
        }

        $gateway = $transaction->getGateway();

        if (!$gateway) {
            return false;
        }

        /** @phpstan-ignore-next-line method.notFound (supportsCapture() is declared on GatewayInterface, which legacy craft\commerce\base\Gateway implements via the class_alias chain, which PHPStan can't trace) */
        if (!$gateway->supportsCapture()) {
            return false;
        }

        // And only if we don't have a successful refund transaction for this order already
        return !$this->query()
            ->where([
                'type' => TransactionRecord::TYPE_CAPTURE,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'orderId' => $transaction->orderId,
                'parentId' => $transaction->id,
            ])
            ->exists();
    }

    /**
     * Returns true if a specific transaction can be refunded.
     */
    public function canRefundTransaction(Transaction $transaction): bool
    {
        // Can refund only successful purchase or capture transactions
        if (!in_array($transaction->type, [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE], true)) {
            return false;
        }

        if ($transaction->status !== TransactionRecord::STATUS_SUCCESS) {
            return false;
        }

        $gateway = $transaction->getGateway();

        if (!$gateway) {
            return false;
        }

        /** @phpstan-ignore-next-line method.notFound (supportsRefund() is declared on GatewayInterface, which legacy craft\commerce\base\Gateway implements via the class_alias chain, which PHPStan can't trace) */
        if (!$gateway->supportsRefund()) {
            return false;
        }

        // Allow gateways to help determine if a transaction can be refunded
        if (!$gateway->transactionSupportsRefund($transaction)) {
            return false;
        }

        return $this->refundableAmountForTransaction($transaction) > 0;
    }

    /**
     * Return the refundable amount for a transaction.
     */
    public function refundableAmountForTransaction(Transaction $transaction): float
    {
        // We need to use the payment currency to calculate the refundable amount
        $teller = app(Currencies::class)->getTeller($transaction->paymentCurrency);

        $amount = DB::table(Table::TRANSACTIONS)
            ->where([
                'type' => TransactionRecord::TYPE_REFUND,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'orderId' => $transaction->orderId,
                'parentId' => $transaction->id,
            ])
            ->sum('paymentAmount');

        return (float)$teller->subtract($transaction->paymentAmount, $amount);
    }

    /**
     * Create a transaction either from an order or a parent transaction. At least one must be present.
     *
     * @param Order|null $order Order that the transaction is a part of. Ignored, if `$parentTransaction` is specified.
     * @param Transaction|null $parentTransaction Parent transaction, if this transaction is a child. Required, if `$order` is not specified.
     * @param string|null $typeOverride The type of transaction. If set, this overrides the type of the parent transaction, or sets the type when no parentTransaction is passed.
     * @throws TransactionException if neither `$order` or `$parentTransaction` is specified.
     */
    public function createTransaction(?Order $order = null, ?Transaction $parentTransaction = null, ?string $typeOverride = null): Transaction
    {
        if (!$order && !$parentTransaction) {
            throw new TransactionException('Tried to create a transaction without order or parent transaction');
        }

        $transaction = new Transaction();
        $transaction->status = TransactionRecord::STATUS_PENDING;

        if ($parentTransaction) {
            // Assume parent values instead of Order values.
            $transaction->parentId = $parentTransaction->id;
            $transaction->gatewayId = $parentTransaction->gatewayId;
            $transaction->amount = $parentTransaction->amount;
            $transaction->currency = $parentTransaction->currency;
            $transaction->paymentAmount = $parentTransaction->paymentAmount;
            $transaction->paymentCurrency = $parentTransaction->paymentCurrency;
            $transaction->paymentRate = $parentTransaction->paymentRate;
            $transaction->setOrder($parentTransaction->getOrder());
            $transaction->reference = $parentTransaction->reference;
            $transaction->type = $parentTransaction->type;
        } else {
            $paymentCurrency = app(PaymentCurrencies::class)->getPaymentCurrencyByIso($order->paymentCurrency, $order->getStore()->id);
            $currency = app(PaymentCurrencies::class)->getPaymentCurrencyByIso($order->currency, $order->getStore()->id);

            /** @var Gateway $gateway */
            /** @phpstan-ignore-next-line varTag.nativeType (legacy craft\commerce\base\Gateway implements GatewayInterface via the class_alias chain, which PHPStan can't trace) */
            $gateway = $order->getGateway();
            $transaction->gatewayId = $gateway->id;

            // Gets the outstanding balance, unless the order had a paymentAmount set in this request
            $transaction->currency = $currency->iso;
            $transaction->paymentCurrency = $paymentCurrency->iso;

            // Payment amount is the amount in the paymentCurrency
            $transaction->paymentAmount = Currency::round($order->getPaymentAmount(), $paymentCurrency);
            $amount = $transaction->paymentAmount;

            if ($currency->iso !== $paymentCurrency->iso) {
                $tellerTo = app(Currencies::class)->getTeller($paymentCurrency->iso);
                $paymentAmount = $tellerTo->convertToMoney($transaction->paymentAmount);
                $amount = app(PaymentCurrencies::class)->convertAmount($paymentAmount, $currency->iso, $order->getStore()->id);
                $amount = (float)$tellerTo->convertToString($amount);
            }

            // Amount is always in the base currency
            $transaction->amount = $amount;

            $transaction->setOrder($order);

            // Capture historical rate
            $transaction->paymentRate = app(PaymentCurrencies::class)->getRateFor($paymentCurrency, $transaction);
        }

        $user = currentUserElement();

        if ($user) {
            $transaction->userId = $user->id;
        }

        if ($typeOverride) {
            $transaction->type = $typeOverride;
        }

        // Raise 'afterCreateTransaction' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getTransactions()->hasEventHandlers(self::EVENT_AFTER_CREATE_TRANSACTION)) {
            $event = new TransactionEvent(transaction: $transaction);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getTransactions()->trigger(self::EVENT_AFTER_CREATE_TRANSACTION, $event);
        }

        return $transaction;
    }

    /**
     * Delete a transaction by id.
     */
    public function deleteTransactionById(int $id): bool
    {
        $record = TransactionRecord::find($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * @return Transaction[]
     */
    public function getAllTopLevelTransactionsByOrderId(int $orderId): array
    {
        $transactions = $this->getAllTransactionsByOrderId($orderId);

        foreach ($transactions as $key => $transaction) {
            // Remove transactions that have a parentId
            if ($transaction->parentId) {
                unset($transactions[$key]);
            }
        }

        return $transactions;
    }

    /**
     * Returns all transactions for an order, per the order's ID.
     *
     * @return Transaction[]
     */
    public function getAllTransactionsByOrderId(int $orderId): array
    {
        return $this->query()
            ->where('orderId', $orderId)
            ->get()
            ->map(fn($row) => new Transaction((array)$row))
            ->all();
    }

    /**
     * Get all children transactions, per a parent transaction's ID.
     *
     * @return Transaction[]
     */
    public function getChildrenByTransactionId(int $transactionId): array
    {
        return $this->query()
            ->where('parentId', $transactionId)
            ->get()
            ->map(fn($row) => new Transaction((array)$row))
            ->all();
    }

    /**
     * Get a transaction by its hash.
     */
    public function getTransactionByHash(string $hash): ?Transaction
    {
        $result = $this->query()->where('hash', $hash)->first();

        return $result ? new Transaction((array)$result) : null;
    }

    /**
     * Get a transaction by its reference and status.
     */
    public function getTransactionByReferenceAndStatus(string $reference, string $status): ?Transaction
    {
        $result = $this->query()->where(compact('reference', 'status'))->first();

        return $result ? new Transaction((array)$result) : null;
    }

    /**
     * Get a transaction by its reference.
     */
    public function getTransactionByReference(string $reference): ?Transaction
    {
        $result = $this->query()->where(compact('reference'))->first();

        return $result ? new Transaction((array)$result) : null;
    }

    /**
     * Get a transaction by its ID.
     */
    public function getTransactionById(int $id): ?Transaction
    {
        $result = $this->query()->where('id', $id)->first();

        return $result ? new Transaction((array)$result) : null;
    }

    /**
     * Returns true if a transaction or a direct child of the transaction is successful.
     */
    public function isTransactionSuccessful(Transaction $transaction): bool
    {
        if ($transaction->status === TransactionRecord::STATUS_SUCCESS) {
            return true;
        }

        return $this->query()
            ->where([
                'parentId' => $transaction->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'orderId' => $transaction->orderId,
            ])
            ->exists();
    }

    /**
     * Save a transaction.
     *
     * @throws TransactionException if an attempt is made to modify an existing transaction
     */
    public function saveTransaction(Transaction $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            throw new TransactionException('Transactions cannot be modified.');
        }

        if ($runValidation && !$model->validate()) {
            Log::info('Transaction not saved due to validation error.');

            return false;
        }

        $fields = [
            'orderId',
            'hash',
            'gatewayId',
            'type',
            'status',
            'amount',
            'currency',
            'paymentAmount',
            'paymentCurrency',
            'paymentRate',
            'reference',
            'message',
            'note',
            'code',
            'response',
            'userId',
            'parentId',
        ];

        $record = new TransactionRecord();

        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->save();
        $model->id = $record->id;

        if ($model->status === TransactionRecord::STATUS_SUCCESS) {
            $model->getOrder()->updateOrderPaidInformation();
        }

        if ($model->status === TransactionRecord::STATUS_PROCESSING) {
            $model->getOrder()->markAsComplete();
        }

        $model->getOrder()->setTransactions(null); // clear the local cache of transactions from the order.

        // Raise 'afterSaveTransaction' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getTransactions()->hasEventHandlers(self::EVENT_AFTER_SAVE_TRANSACTION)) {
            $event = new TransactionEvent(transaction: $model);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getTransactions()->trigger(self::EVENT_AFTER_SAVE_TRANSACTION, $event);
        }

        return true;
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadTransactionsForOrders(array $orders): array
    {
        $orderIds = collect($orders)->pluck('id')->filter()->all();
        $transactionResults = $this->query()->whereIn('orderId', $orderIds)->get();

        $transactions = [];

        foreach ($transactionResults as $result) {
            $transaction = new Transaction((array)$result);
            $transactions[$transaction->orderId] ??= [];
            $transactions[$transaction->orderId][] = $transaction;
        }

        foreach ($orders as $key => $order) {
            if (isset($transactions[$order->id])) {
                $order->setTransactions($transactions[$order->id]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    private function query(): Builder
    {
        return DB::table(Table::TRANSACTIONS)
            ->select([
                'amount',
                'code',
                'currency',
                'dateCreated',
                'dateUpdated',
                'gatewayId',
                'hash',
                'id',
                'message',
                'note',
                'orderId',
                'parentId',
                'paymentAmount',
                'paymentCurrency',
                'paymentRate',
                'reference',
                'response',
                'status',
                'type',
                'userId',
            ])
            ->orderBy('id');
    }
}
