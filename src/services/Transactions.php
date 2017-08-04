<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\TransactionEvent;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use yii\base\Component;

/**
 * Transaction service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Transactions extends Component
{
    // Constants
    // =========================================================================
    /**
     * @event TransactionEvent The event that is triggered after a transaction has been saved.
     */
    const EVENT_AFTER_SAVE_TRANSACTION = 'afterSaveTransaction';

    // Public Methods
    // =========================================================================
    /**
     * @param int $id
     *
     * @return Transaction|null
     */
    public function getTransactionById($id)
    {
        $result = $this->_createTransactionQuery()
            ->where(['id' => $id])
            ->one();

        if ($result) {
            return new Transaction($result);
        }

        return null;
    }

    /**
     * @param string $hash
     *
     * @return Transaction|null
     */
    public function getTransactionByHash($hash)
    {
        $result = $this->_createTransactionQuery()
            ->where(['hash' => $hash])
            ->one();

        if ($result) {
            return new Transaction($result);
        }

        return null;
    }

    /**
     * @param int $orderId
     *
     * @return Transaction[]
     */
    public function getAllTransactionsByOrderId($orderId): array
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
     * Returns true if a specific transaction can be refunded.
     *
     * @param Transaction $transaction
     *
     * @return bool
     */
    public function canRefundTransaction(Transaction $transaction): bool {

        // Can refund only successful purchase or capture transactions
        if (!in_array($transaction->type, [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]))
        {
            return false;
        }

        if ($transaction->status != TransactionRecord::STATUS_SUCCESS) {
            return false;
        }

        $gateway = $transaction->getGateway();

        if (!$gateway->supportsRefund()) {
            return false;
        }

        // And only if we don't have a successful refund transaction for this order already
        return !$this->_createTransactionQuery()
            ->where(['type' => TransactionRecord::TYPE_REFUND,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'orderId' => $transaction->orderId])
            ->exists();
    }

    /**
     * Returns true if a specific transaction can be refunded.
     *
     * @param Transaction $transaction
     *
     * @return bool
     */
    public function canCaptureTransaction(Transaction $transaction): bool {

        // Can refund only successful authorize transactions
        if ($transaction->type != TransactionRecord::TYPE_AUTHORIZE || $transaction->status != TransactionRecord::STATUS_SUCCESS) {
            return false;
        }

        $gateway = $transaction->getGateway();

        if (!$gateway->supportsCapture()) {
            return false;
        }

        // And only if we don't have a successful refund transaction for this order already
        return !$this->_createTransactionQuery()
            ->where(['type' => TransactionRecord::TYPE_CAPTURE,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'orderId' => $transaction->orderId])
            ->exists();
    }

    /**
     * @param Order $order
     *
     * @return Transaction
     */
    public function createTransaction(Order $order)
    {
        $paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($order->paymentCurrency);
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($order->currency);

        $paymentAmount = $order->outstandingBalance() * $paymentCurrency->rate;

        $transaction = new Transaction();
        $transaction->status = TransactionRecord::STATUS_PENDING;
        $transaction->amount = $order->outstandingBalance();
        $transaction->orderId = $order->id;
        $transaction->currency = $currency->iso;
        $transaction->paymentAmount = Currency::round($paymentAmount, $paymentCurrency);
        $transaction->paymentCurrency = $paymentCurrency->iso;
        $transaction->paymentRate = $paymentCurrency->rate;
        $transaction->gatewayId = $order->gatewayId;

        $user = Craft::$app->getUser()->getIdentity();

        if ($user) {
            $transaction->userId = $user->id;
        }

        return $transaction;
    }

    /**
     * @param Transaction $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveTransaction(Transaction $model)
    {
        if ($model->id) {
            $record = TransactionRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No transaction exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new TransactionRecord();
        }

        $fields = [
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
            'code',
            'response',
            'userId',
            'parentId'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            $record->save(false);
            $model->id = $record->id;

            // Raise 'afterSaveTransaction' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_TRANSACTION)) {
                $this->trigger(self::EVENT_AFTER_SAVE_TRANSACTION, new TransactionEvent([
                    'transaction' => $model
                ]));
            }

            return true;
        }

        return false;
    }


    /**
     * @param Transaction $transaction
     *
     * @return bool
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        $record = TransactionRecord::findOne($transaction->id);

        if ($record) {
            return (bool) $record->delete();
        }

        return false;
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
                'code',
                'response',
                'userId',
                'parentId'
            ])
            ->from(['{{%commerce_transactions}}']);
    }
}
