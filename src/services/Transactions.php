<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\TransactionEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Transaction service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Transactions extends Component
{
    /**
     * @event TransactionEvent The event that is triggered after a transaction has been saved.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Transactions;
     * use craft\commerce\models\Transaction;
     * use yii\base\Event;
     *
     * Event::on(
     *     Transactions::class,
     *     Transactions::EVENT_AFTER_SAVE_TRANSACTION,
     *     function(TransactionEvent $event) {
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *
     *         // Run custom logic for failed transactions
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_TRANSACTION = 'afterSaveTransaction';

    /**
     * @event TransactionEvent The event that is triggered after a transaction has been created.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Transactions;
     * use craft\commerce\models\Transaction;
     * use yii\base\Event;
     *
     * Event::on(
     *     Transactions::class,
     *     Transactions::EVENT_AFTER_CREATE_TRANSACTION,
     *     function(TransactionEvent $event) {
     *         // @var Transaction $transaction
     *         $transaction = $event->transaction;
     *
     *         // Run custom logic depending on the transaction type
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_CREATE_TRANSACTION = 'afterCreateTransaction';


    /**
     * Returns true if a specific transaction can be refunded.
     *
     * @param Transaction $transaction the transaction
     * @return bool
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

        if (!$gateway->supportsCapture()) {
            return false;
        }

        // And only if we don't have a successful refund transaction for this order already
        return !$this->_createTransactionQuery()
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
     *
     * @param Transaction $transaction the transaction
     * @return bool
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

        if (!$gateway->supportsRefund()) {
            return false;
        }

        return ($this->refundableAmountForTransaction($transaction) > 0);
    }

    /**
     * Return the refundable amount for a transaction.
     *
     * @param Transaction $transaction
     * @return float
     */
    public function refundableAmountForTransaction(Transaction $transaction): float
    {
        $amount = (new Query())
            ->where([
                'type' => TransactionRecord::TYPE_REFUND,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'orderId' => $transaction->orderId,
                'parentId' => $transaction->id,
            ])
            ->from([Table::TRANSACTIONS])
            ->sum('[[paymentAmount]]');

        return $transaction->paymentAmount - $amount;
    }

    /**
     * Create a transaction either from an order or a parent transaction. At least one must be present.
     *
     * @param Order|null $order Order that the transaction is a part of. Ignored, if `$parentTransaction` is specified.
     * @param Transaction|null $parentTransaction Parent transaction, if this transaction is a child. Required, if `$order` is not specified.
     * @param null $typeOverride The type of transaction. If set, this overrides the type of the parent transaction, or sets the type when no parentTransaction is passed.
     * @return Transaction
     * @throws TransactionException if neither `$order` or `$parentTransaction` is specified.
     * @throws CurrencyException
     * @throws InvalidConfigException
     */
    public function createTransaction(Order $order = null, Transaction $parentTransaction = null, $typeOverride = null): Transaction
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
            $paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($order->paymentCurrency);
            $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($order->currency);

            /** @var Gateway $gateway */
            $gateway = $order->getGateway();
            $transaction->gatewayId = $gateway->id;

            // Gets the outstanding balance, unless the order had a paymentAmount set in this request
            $transaction->currency = $currency->iso;
            $transaction->paymentCurrency = $paymentCurrency->iso;

            // Payment amount is the amount in the paymentCurrency
            $transaction->paymentAmount = Currency::round($order->getPaymentAmount(), $paymentCurrency);

            // Amount is always in the base currency
            $amount = Plugin::getInstance()->getPaymentCurrencies()->convertCurrency($transaction->paymentAmount, $transaction->paymentCurrency, $transaction->currency);
            $transaction->amount = Currency::round($amount, $currency);

            // Capture historical rate
            $transaction->paymentRate = $paymentCurrency->rate;

            $transaction->setOrder($order);
        }

        $user = Craft::$app->getUser()->getIdentity();

        if ($user) {
            $transaction->userId = $user->id;
        }

        if ($typeOverride) {
            $transaction->type = $typeOverride;
        }

        // Raise 'afterCreateTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CREATE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_CREATE_TRANSACTION, new TransactionEvent([
                'transaction' => $transaction,
            ]));
        }

        return $transaction;
    }

    /**
     * Delete a transaction.
     *
     * @param Transaction $transaction the transaction to delete
     * @return bool
     * @throws Throwable
     * @throws StaleObjectException
     * @deprecated in 4.0. Use [[deleteTransactionById]] instead.
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        $record = TransactionRecord::findOne($transaction->id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * Delete a transaction by id.
     *
     * @param int $id the transaction ID
     * @return bool
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteTransactionById(int $id): bool
    {
        $record = TransactionRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * @param int $orderId the order's ID
     * @return array|Transaction[]
     * @noinspection PhpUnused
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
     * @param int $orderId the order's ID
     * @return Transaction[]
     */
    public function getAllTransactionsByOrderId(int $orderId): array
    {
        $rows = $this->_createTransactionQuery()
            ->where(['orderId' => $orderId])
            ->all();

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] = new Transaction($row);
        }

        return $transactions;
    }

    /**
     * Get all children transactions, per a parent transaction's ID.
     *
     * @param int $transactionId the parent transaction's ID
     * @return array
     */
    public function getChildrenByTransactionId(int $transactionId): array
    {
        $rows = $this->_createTransactionQuery()
            ->where(['parentId' => $transactionId])
            ->all();

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] = new Transaction($row);
        }

        return $transactions;
    }

    /**
     * Get a transaction by its hash.
     *
     * @param string $hash the hash of transaction
     * @return Transaction|null
     */
    public function getTransactionByHash(string $hash): ?Transaction
    {
        $result = $this->_createTransactionQuery()
            ->where(['hash' => $hash])
            ->one();

        return $result ? new Transaction($result) : null;
    }

    /**
     * Get a transaction by its reference and status.
     *
     * @param string $reference the transaction reference
     * @param string $status the transaction status
     * @return Transaction|null
     */
    public function getTransactionByReferenceAndStatus(string $reference, string $status): ?Transaction
    {
        $result = $this->_createTransactionQuery()
            ->where(compact('reference', 'status'))
            ->one();

        return $result ? new Transaction($result) : null;
    }

    /**
     * Get a transaction by its reference.
     *
     * @param string $reference the transaction reference
     * @return Transaction|null
     */
    public function getTransactionByReference(string $reference)
    {
        $result = $this->_createTransactionQuery()
            ->where(compact('reference'))
            ->one();

        return $result ? new Transaction($result) : null;
    }

    /**
     * Get a transaction by its ID.
     *
     * @param int $id the ID of transaction
     * @return Transaction|null
     */
    public function getTransactionById(int $id): ?Transaction
    {
        $result = $this->_createTransactionQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new Transaction($result) : null;
    }

    /**
     * Returns true if a transaction or a direct child of the transaction is successful.
     *
     * @param Transaction $transaction
     * @return bool
     */
    public function isTransactionSuccessful(Transaction $transaction): bool
    {
        if ($transaction->status === TransactionRecord::STATUS_SUCCESS) {
            return true;
        }

        return $this->_createTransactionQuery()
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
     * @param Transaction $model the transaction model
     * @param bool $runValidation should we validate this transaction before saving.
     * @return bool
     * @throws Throwable
     * @throws TransactionException if an attempt is made to modify an existing transaction
     * @throws OrderStatusException
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function saveTransaction(Transaction $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            throw new TransactionException('Transactions cannot be modified.');
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Transaction not saved due to validation error.', __METHOD__);

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

        $record->save(false);
        $model->id = $record->id;

        if ($model->status === TransactionRecord::STATUS_SUCCESS) {
            $model->order->updateOrderPaidInformation();
        }

        if ($model->status === TransactionRecord::STATUS_PROCESSING) {
            $model->order->markAsComplete();
        }

        $model->getOrder()->setTransactions(null); // clear the local cache of transactions from the order.

        // Raise 'afterSaveTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_SAVE_TRANSACTION, new TransactionEvent([
                'transaction' => $model,
            ]));
        }

        return true;
    }

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadTransactionsForOrders(array $orders): array
    {
        $orderIds = array_filter(ArrayHelper::getColumn($orders, 'id'));
        $transactionResults = $this->_createTransactionQuery()->andWhere(['orderId' => $orderIds])->all();

        $transactions = [];

        foreach ($transactionResults as $result) {
            $transaction = new Transaction($result);
            $transactions[$transaction->orderId] = $transactions[$transaction->orderId] ?? [];
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

    /**
     * Returns a Query object prepped for retrieving Transactions.
     *
     * @return Query The query object.
     */
    private function _createTransactionQuery(): Query
    {
        return (new Query())
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
            ->from([Table::TRANSACTIONS])
            ->orderBy(['id' => SORT_ASC]);
    }
}
