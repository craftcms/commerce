<?php
namespace Craft;

/**
 * Class Stripey_TransactionService
 * @package Craft
 */
class Stripey_TransactionService extends BaseApplicationComponent
{
    /**
     * @param int $id
     * @return Stripey_TransactionModel
     */
    public function getById($id)
    {
        $record = Stripey_TransactionRecord::model()->findById($id);
        return Stripey_TransactionModel::populateModel($record);
    }

    /**
     * @param int $orderId
     * @return Stripey_TransactionModel[]
     */
    public function getAllByOrderId($orderId)
    {
        $records = Stripey_TransactionRecord::model()->findAllByAttributes(array('orderId' => $orderId));
        return Stripey_TransactionModel::populateModels($records);
    }
}