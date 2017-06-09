<?php
namespace Craft;

use Commerce\Helpers\CommerceCurrencyHelper;

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
class Commerce_TransactionsService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_TransactionModel|null
     */
    public function getTransactionById($id)
    {
        $result = Commerce_TransactionRecord::model()->findById($id);

        if ($result) {
            return Commerce_TransactionModel::populateModel($result);
        }

        return null;

    }

    /**
     * @param string $hash
     *
     * @return Commerce_TransactionModel|null
     */
    public function getTransactionByHash($hash)
    {
        $result = Commerce_TransactionRecord::model()->findByAttributes(['hash' => $hash]);

        if ($result) {
            return Commerce_TransactionModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param int $orderId
     *
     * @return Commerce_TransactionModel[]
     */
    public function getAllTransactionsByOrderId($orderId)
    {
        $records = Commerce_TransactionRecord::model()->findAllByAttributes(['orderId' => $orderId]);

        return Commerce_TransactionModel::populateModels($records);
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return bool
     */
    public function transactionExists($criteria = [])
    {
        return Commerce_TransactionRecord::model()->exists($criteria);
    }

    /**
     * @param Commerce_OrderModel $order
     *
     * @return Commerce_TransactionModel
     */
    public function createTransaction(Commerce_OrderModel $order)
    {
        $paymentCurrency = craft()->commerce_paymentCurrencies->getPaymentCurrencyByIso($order->paymentCurrency);
        $currency = craft()->commerce_paymentCurrencies->getPaymentCurrencyByIso($order->currency);

        $paymentAmount = $order->outstandingBalance() * $paymentCurrency->rate;

        $transaction = new Commerce_TransactionModel;
        $transaction->setOrder($order);
        $transaction->status = Commerce_TransactionRecord::STATUS_PENDING;
        $transaction->amount = $order->outstandingBalance();
        $transaction->currency = $currency->iso;
        $transaction->paymentAmount = CommerceCurrencyHelper::round($paymentAmount, $paymentCurrency);
        $transaction->paymentCurrency = $paymentCurrency->iso;
        $transaction->paymentRate = $paymentCurrency->rate;
        $transaction->paymentMethodId = $order->paymentMethodId;

        $user = craft()->userSession->getUser();
        if ($user)
        {
            $transaction->userId = $user->id;
        }

        $event = new Event($this, [
            'transaction' => $transaction
        ]);
        $this->onCreateTransaction($event);

        return $transaction;
    }

    /**
     * @param Commerce_TransactionModel $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveTransaction(Commerce_TransactionModel $model)
    {
        if ($model->id) {
            $record = Commerce_TransactionRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No transaction exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_TransactionRecord();
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
     * @param Commerce_TransactionModel $transaction
     */
    public function deleteTransaction(Commerce_TransactionModel $transaction)
    {
        Commerce_TransactionRecord::model()->deleteByPk($transaction->id);
    }


    /**
     * Event: When a new transactions is created for an order.
     * Event params: transaction(Commerce_TransactionModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onCreateTransaction(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['transaction']) || !($params['transaction'] instanceof Commerce_TransactionModel)) {
            throw new Exception('onCreateTransaction event requires "transaction" param with Commerce_TransactionModel instance');
        }
        $this->raiseEvent('onCreateTransaction', $event);
    }


    /**
     * Event: After successfully saving a transaction
     * Event params: transaction(Commerce_TransactionModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveTransaction(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['transaction']) || !($params['transaction'] instanceof Commerce_TransactionModel)) {
            throw new Exception('onSaveTransaction event requires "transaction" param with Commerce_TransactionModel instance');
        }
        $this->raiseEvent('onSaveTransaction', $event);
    }

}
