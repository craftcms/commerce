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
use craft\commerce\errors\TransactionException;
use craft\commerce\events\TransactionEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use yii\base\Component;

/**
 * Transaction service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Transactions extends Component
{
    // Constants
    // =========================================================================
    /**
     * @event TransactionEvent The event that is triggered after a transaction has been saved.
     *
     * Plugins can get notified after a transaction has been saved.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Transactions;
     * use yii\base\Event;
     *
     * Event::on(Transactions::class, Transactions::EVENT_AFTER_SAVE_TRANSACTION, function(TransactionEvent $e) {
     *     // Do something - perhaps run our custom logic for failed transactions
     * });
     * ```
     */
    const EVENT_AFTER_SAVE_TRANSACTION = 'afterSaveTransaction';

    /**
     * @event TransactionEvent The event that is triggered after a transaction has been created.
     *
     * Plugins can get notified after a transaction has been created.
     *
     * ```php
     * use craft\commerce\events\TransactionEvent;
     * use craft\commerce\services\Transactions;
     * use yii\base\Event;
     *
     * Event::on(Transactions::class, Transactions::EVENT_AFTER_CREATE_TRANSACTION, function(TransactionEvent $e) {
     *     // Do something - perhaps run our custom logic depending on the transaction type
     * });
     * ```
     */
    const EVENT_AFTER_CREATE_TRANSACTION = 'afterCreateTransaction';

    // Public Methods
    // =========================================================================

    /**
     * Returns true if a specific transaction can be refunded.
     *
     * @param Transaction $transaction the transaction
     * @return bool
     */
    public function canCaptureTransaction(Transaction $transaction): bool
    {
        // Can refund only successful authorize transactions
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
                'parentId' => $transaction->id
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
                'parentId' => $transaction->id
            ])
            ->from([Table::TRANSACTIONS])
            ->sum('[[paymentAmount]]');

        return $transaction->paymentAmount - $amount;
    }

    /**
     * Create a transaction either from an order or a parent transaction. At least one must be present.
     *
     * @param Order $order Order that the transaction is a part of. Ignored, if `$parentTransaction` is specified.
     * @param Transaction $parentTransaction Parent transaction, if this transaction is a child. Required, if `$order` is not specified.
     * @param string $typeOverride The type of transaction. If set, this overrides the type of the parent transaction, or sets the type when no parentTransaction is passed.
     * @return Transaction
     * @throws TransactionException if neither `$order` or `$parentTransaction` is specified.
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
            $paymentAmount = $order->getOutstandingBalance() * $paymentCurrency->rate;

            /** @var Gateway $gateway */
            $gateway = $order->getGateway();

            $transaction->gatewayId = $gateway->id;
            $transaction->amount = $order->getOutstandingBalance();
            $transaction->currency = $currency->iso;
            $transaction->paymentAmount = Currency::round($paymentAmount, $paymentCurrency);
            $transaction->paymentCurrency = $paymentCurrency->iso;
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
                'transaction' => $transaction
            ]));
        }

        return $transaction;
    }

    /**
     * Delete a transaction.
     *
     * @param Transaction $transaction the transaction to delete
     * @return bool
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
    public function getTransactionByHash(string $hash)
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
    public function getTransactionByReferenceAndStatus(string $reference, string $status)
    {
        $result = $this->_createTransactionQuery()
            ->where(compact('reference', 'status'))
            ->one();

        return $result ? new Transaction($result) : null;
    }

    /**
     * Get a transaction by its ID.
     *
     * @param int $id the ID of transaction
     * @return Transaction|null
     */
    public function getTransactionById(int $id)
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
                'orderId' => $transaction->orderId
            ])
            ->exists();
    }

    /**
     * Save a transaction.
     *
     * @param Transaction $model the transaction model
     * @param bool $runValidation should we validate this transaction before saving.
     * @return bool
     * @throws TransactionException if an attempt is made to modify an existing transaction
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
            'parentId'
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

        // Raise 'afterSaveTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_SAVE_TRANSACTION, new TransactionEvent([
                'transaction' => $model
            ]));
        }

        return true;
    }

    // Private methods
    // =========================================================================
    /**
     * Returns a Query object prepped for retrieving Transactions.
     *
     * @return Query The query object.
     */
    private function _createTransactionQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
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
                'dateCreated',
                'dateUpdated',
            ])
            ->from([Table::TRANSACTIONS])
            ->orderBy(['id' => SORT_ASC]);
    }
}
