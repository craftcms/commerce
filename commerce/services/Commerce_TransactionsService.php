<?php
namespace Craft;

/**
 * Transaction service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_TransactionsService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_TransactionModel
	 */
	public function getById ($id)
	{
		$record = Commerce_TransactionRecord::model()->findById($id);

		return Commerce_TransactionModel::populateModel($record);
	}

	/**
	 * @param string $hash
	 *
	 * @return Commerce_TransactionModel
	 */
	public function getByHash ($hash)
	{
		$record = Commerce_TransactionRecord::model()->findByAttributes(['hash' => $hash]);

		return Commerce_TransactionModel::populateModel($record);
	}

	/**
	 * @param int $orderId
	 *
	 * @return Commerce_TransactionModel[]
	 */
	public function getAllByOrderId ($orderId)
	{
		$records = Commerce_TransactionRecord::model()->findAllByAttributes(['orderId' => $orderId]);

		return Commerce_TransactionModel::populateModels($records);
	}

	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return bool
	 */
	public function exists ($criteria = [])
	{
		return Commerce_TransactionRecord::model()->exists($criteria);
	}

	/**
	 * @param Commerce_OrderModel $order
	 *
	 * @return Commerce_TransactionModel
	 */
	public function create (Commerce_OrderModel $order)
	{
		$transaction = new Commerce_TransactionModel;
		$transaction->status = Commerce_TransactionRecord::PENDING;
		$transaction->amount = round($order->totalPrice, 2);
		$transaction->orderId = $order->id;
		$transaction->paymentMethodId = $order->paymentMethodId;

		$user = craft()->userSession->getUser();
		if ($user)
		{
			$transaction->userId = $user->id;
		}

		return $transaction;
	}

	/**
	 * @param Commerce_TransactionModel $model
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save (Commerce_TransactionModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_TransactionRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No transaction exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
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
			'reference',
			'message',
			'response',
			'userId',
			'parentId'
		];
		foreach ($fields as $field)
		{
			$record->$field = $model->$field;
		}

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			$record->save(false);
			$model->id = $record->id;

			return true;
		}

		return false;
	}

	/**
	 * @param Commerce_TransactionModel $transaction
	 */
	public function delete (Commerce_TransactionModel $transaction)
	{
		Commerce_TransactionRecord::model()->deleteByPk($transaction->id);
	}

}