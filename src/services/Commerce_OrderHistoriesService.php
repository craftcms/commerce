<?php
namespace Craft;

/**
 * Order history service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrderHistoriesService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_OrderHistoryModel
	 */
	public function getById ($id)
	{
		$record = Commerce_OrderHistoryRecord::model()->findById($id);

		return Commerce_OrderHistoryModel::populateModel($record);
	}

	/**
	 * @param array $attr
	 *
	 * @return Commerce_OrderHistoryModel
	 */
	public function getByAttributes (array $attr)
	{
		$record = Commerce_OrderHistoryRecord::model()->findByAttributes($attr);

		return Commerce_OrderHistoryModel::populateModel($record);
	}

	/**
	 * @param \CDbCriteria|array $criteria
	 *
	 * @return Commerce_OrderHistoryModel[]
	 */
	public function getAll (array $criteria = [])
	{
		$records = Commerce_OrderHistoryRecord::model()->findAll($criteria);

		return Commerce_OrderHistoryModel::populateModels($records);
	}

	/**
	 * @param Commerce_OrderModel $order
	 * @param int               $oldStatusId
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function createFromOrder (Commerce_OrderModel $order, $oldStatusId)
	{
		$orderHistoryModel = new Commerce_OrderHistoryModel();
		$orderHistoryModel->orderId = $order->id;
		$orderHistoryModel->prevStatusId = $oldStatusId;
		$orderHistoryModel->newStatusId = $order->orderStatusId;
		$orderHistoryModel->customerId = craft()->commerce_customers->getCustomerId();
		$orderHistoryModel->message = $order->message;

		if (!$this->save($orderHistoryModel))
		{
			return false;
		}

		//raising event on status change
		$event = new Event($this, [
			'orderHistory' => $orderHistoryModel,
			'order'        => $order
		]);
		$this->onStatusChange($event);

		return true;
	}

	/**
	 * @param Commerce_OrderHistoryModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save (Commerce_OrderHistoryModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_OrderHistoryRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No order history exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_OrderHistoryRecord();
		}

		$record->message = $model->message;
		$record->newStatusId = $model->newStatusId;
		$record->prevStatusId = $model->prevStatusId;
		$record->customerId = $model->customerId;
		$record->orderId = $model->orderId;

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			// Save it!
			$record->save(false);

			// Now that we have a record ID, save it on the model
			$model->id = $record->id;

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Event method
	 * Event params: order (Commerce_OrderModel), orderHistoryModel
	 * (Commerce_OrderHistoryModel)
	 *
	 * @param \CEvent $event
	 *
	 * @throws \CException
	 */
	public function onStatusChange (\CEvent $event)
	{
		$params = $event->params;
		if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
		{
			throw new Exception('onStatusChange event requires "order" param with OrderModel instance');
		}

		if (empty($params['orderHistory']) || !($params['orderHistory'] instanceof Commerce_OrderHistoryModel))
		{
			throw new Exception('onStatusChange event requires "orderHistory" param with OrderHistoryModel instance');
		}

		$this->raiseEvent('onStatusChange', $event);
	}

	/**
	 * @param int $id
	 *
	 * @throws \CDbException
	 */
	public function deleteById ($id)
	{
		Commerce_OrderHistoryRecord::model()->deleteByPk($id);
	}
}