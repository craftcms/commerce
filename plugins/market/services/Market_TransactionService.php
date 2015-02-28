<?php
namespace Craft;

/**
 * Class Market_TransactionService
 *
 * @package Craft
 */
class Market_TransactionService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @return Market_TransactionModel
	 */
	public function getById($id)
	{
		$record = Market_TransactionRecord::model()->findById($id);
		return Market_TransactionModel::populateModel($record);
	}

	/**
	 * @param int $orderId
	 * @return Market_TransactionModel[]
	 */
	public function getAllByOrderId($orderId)
	{
		$records = Market_TransactionRecord::model()->findAllByAttributes(['orderId' => $orderId]);
		return Market_TransactionModel::populateModels($records);
	}

    /**
     * @param array|\CDbCriteria $criteria
     * @return bool
     */
    public function exists($criteria = [])
    {
        return Market_TransactionRecord::model()->exists($criteria);
    }

	/**
	 * @param Market_OrderModel $order
	 * @return Market_TransactionModel
	 */
	public function create(Market_OrderModel $order)
	{
		$transaction                  = new Market_TransactionModel;
		$transaction->status          = Market_TransactionRecord::PENDING;
		$transaction->amount          = round($order->finalPrice, 2);
		$transaction->orderId         = $order->id;
		$transaction->paymentMethodId = $order->paymentMethodId;

        $user = craft()->userSession->getUser();
        if($user) {
            $transaction->userId = $user->id;
        }

        return $transaction;
	}

	/**
	 * @param Market_TransactionModel $model
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save(Market_TransactionModel $model)
	{
		if ($model->id) {
			$record = Market_TransactionRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No transaction exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_TransactionRecord();
		}

		$fields = ['id', 'orderId', 'hash', 'paymentMethodId', 'type', 'status', 'amount', 'reference', 'message', 'response', 'userId', 'parentId'];
		foreach ($fields as $field) {
			$record->$field = $model->$field;
		}

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors()) {
			$record->save(false);
			$model->id = $record->id;

			return true;
		}

		return false;
	}

	/**
	 * @param Market_TransactionModel $transaction
	 */
	public function delete(Market_TransactionModel $transaction)
	{
		Market_TransactionRecord::model()->deleteByPk($transaction->id);
	}

}