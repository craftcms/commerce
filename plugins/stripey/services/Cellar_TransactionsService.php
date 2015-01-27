<?php
namespace Craft;

class Cellar_TransactionsService extends BaseApplicationComponent
{
    public function getTransaction($id)
    {
        $record = Cellar_TransactionRecord::model()->findByPk($id);

        if ($record) {
            return Cellar_TransactionModel::populateModel($record);
        }

        return null;
    }

    public function getTransactionsByOrderId($orderId)
    {
        $conditions = '
			orderId=:orderId
        ';

        $params = array(
            ':orderId' => $orderId
        );

        $records = Cellar_TransactionRecord::model()->findAll($conditions, $params);

        if ($records) {
            return Cellar_TransactionModel::populateModels($records);
        }

        return null;
    }
}