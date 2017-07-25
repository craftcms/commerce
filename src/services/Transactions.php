<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\TransactionEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
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
        $result = TransactionRecord::findOne($id);

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
        $result = TransactionRecord::find()->where(['hash' => $hash])->one();

        if ($result) {
            return Transaction::populateModel($result);
        }

        return null;
    }

    /**
     * @param int $orderId
     *
     * @return Transaction[]
     */
    public function getAllTransactionsByOrderId($orderId)
    {
        $records = TransactionRecord::find()->where(['orderId' => $orderId])->all();

        return Transaction::populateModels($records);
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
            'paymentMethodId',
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

            $event = new TransactionEvent([
                'transaction' => $model
            ]);

            $this->trigger(self::EVENT_AFTER_SAVE_TRANSACTION, $event);

            return true;
        }

        return false;
    }


    /**
     * @param Transaction $transaction
     *
     * @return false|int
     */
    public function deleteTransaction(Transaction $transaction)
    {
        $record = TransactionRecord::findOne($transaction->id);

        if ($record) {
            return $record->delete();
        }

        return false;
    }

}
