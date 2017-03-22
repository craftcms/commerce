<?php
namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Transaction;
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
    /**
     * @param int $id
     *
     * @return Transaction|null
     */
    public function getTransactionById($id)
    {
        $result = TransactionRecord::model()->findById($id);

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
        $result = TransactionRecord::model()->findByAttributes(['hash' => $hash]);

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
        $records = TransactionRecord::model()->findAllByAttributes(['orderId' => $orderId]);

        return Transaction::populateModels($records);
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return bool
     */
    public function transactionExists($criteria = [])
    {
        return TransactionRecord::model()->exists($criteria);
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
        $transaction->paymentMethodId = $order->paymentMethodId;

        $user = Craft::$app->getUser()->getUser();
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
            $record = TransactionRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No transaction exists with the ID “{id}”',
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

            $event = new Event($this, [
                'transaction' => $model
            ]);
            $this->onSaveTransaction($event);

            return true;
        }

        return false;
    }

    /**
     * Event: After successfully saving a transaction
     * Event params: transaction(Transaction)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveTransaction(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['transaction']) || !($params['transaction'] instanceof Transaction)) {
            throw new Exception('onSaveTransaction event requires "transaction" param with Transaction instance');
        }
        $this->raiseEvent('onSaveTransaction', $event);
    }

    /**
     * @param Transaction $transaction
     */
    public function deleteTransaction(Transaction $transaction)
    {
        TransactionRecord::model()->deleteByPk($transaction->id);
    }

}
