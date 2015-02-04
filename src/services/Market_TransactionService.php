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
	 *
	 * @return Market_TransactionModel
	 */
	public function getById($id)
	{
		$record = Market_TransactionRecord::model()->findById($id);

		return Market_TransactionModel::populateModel($record);
	}

	/**
	 * @param int $orderId
	 *
	 * @return Market_TransactionModel[]
	 */
	public function getAllByOrderId($orderId)
	{
		$records = Market_TransactionRecord::model()->findAllByAttributes(array('orderId' => $orderId));

		return Market_TransactionModel::populateModels($records);
	}
}