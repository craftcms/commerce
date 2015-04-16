<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_OrderStatusService
 *
 * @package Craft
 */
class Market_OrderStatusService extends BaseApplicationComponent
{
	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Market_OrderStatusModel[]
	 */
	public function getAll($criteria = [])
	{
		$orderStatusRecords = Market_OrderStatusRecord::model()->findAll($criteria);
		return Market_OrderStatusModel::populateModels($orderStatusRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_OrderStatusModel
	 */
	public function getById($id)
	{
		$orderStatusRecord = Market_OrderStatusRecord::model()->findById($id);

		return Market_OrderStatusModel::populateModel($orderStatusRecord);
	}

	/**
	 * @param string $handle
	 *
	 * @return Market_OrderStatusModel
	 */
	public function getByHandle($handle)
	{
		$orderStatusRecord = Market_OrderStatusRecord::model()->findByAttributes(['handle' => $handle]);

		return Market_OrderStatusModel::populateModel($orderStatusRecord);
	}

	/**
	 * @param string $orderStatusHandle
	 *
	 * @return Market_OrderStatusModel
	 * @throws Exception
	 */
	public function getByHandleOrOnly($orderStatusHandle = '')
	{
		$orderStatus = $this->getByHandle($orderStatusHandle);

		if ($orderStatusHandle == '' or !$orderStatus->id){

			//Temp: did not pass a orderStatus
			throw new Exception('did not pass a orderStatus');

			MarketPlugin::log("Can not find cart with Order Status of '".$orderStatusHandle."' getting first Order Status.");
			$orderStatus = craft()->market_orderStatus->getFirst();
			if (!$orderStatus->id) {
				throw new Exception('Not one order status found');
			}
		}

		return $orderStatus;
	}

	/**
	 * Get first (default) order status from the DB
	 *
	 * @return Market_OrderStatusModel
	 */
	public function getFirst()
	{
		$orderStatus = Market_OrderStatusRecord::model()->find(['order' => 'id', 'limit' => 1]);

		return Market_OrderStatusModel::populateModel($orderStatus);
	}

	/**
	 * @param Market_OrderStatusModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_OrderStatusModel $model, array $emailsIds)
	{
		if ($model->id) {
			$record = Market_OrderStatusRecord::model()->findById($model->id);
			if (!$record->id) {
				throw new Exception(Craft::t('No order status exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_OrderStatusRecord();
		}

		$record->name        = $model->name;
        $record->handle      = $model->handle;
        $record->color       = $model->color;
        $record->orderTypeId = $model->orderTypeId;
        $record->default     = $model->default;

		$record->validate();
		$model->addErrors($record->getErrors());

        //validating color
        if(!$model->getError('color') && !preg_match('/^[a-fA-F0-9]{6}$/', $model->color)) {
            $model->addError('color', 'Color must contain hex digits only: 0-9 or A-F');
        }

        //validating emails ids
        $criteria = new \CDbCriteria();
        $criteria->addInCondition('id', $emailsIds);
        $exist = Market_EmailRecord::model()->exists($criteria);
		$hasEmails = (boolean) count($emailsIds);

		if (!$exist && $hasEmails) {
			$model->addError('emails', 'One or more emails do not exist in the system.');
		}

        //saving
		if (!$model->hasErrors()) {
            MarketDbHelper::beginStackedTransaction();
			try {
                //only one default status can be among statuses of one order type
                if($record->default) {
                    Market_OrderStatusRecord::model()->updateAll(['default' => 0], 'orderTypeId = :id', ['id' => $record->orderTypeId]);
                }

				// Save it!
				$record->save(false);

                //Delete old links
                if($model->id) {
                    Market_OrderStatusEmailRecord::model()->deleteAllByAttributes(['orderStatusId' => $model->id]);
                }

                //Save new links
                $rows  = array_map(function ($id) use ($record) {
                    return [$id, $record->id];
                }, $emailsIds);
                $cols  = ['emailId', 'orderStatusId'];
                $table = Market_OrderStatusEmailRecord::model()->getTableName();
				craft()->db->createCommand()->insertAll($table, $cols, $rows);

				// Now that we have a calendar ID, save it on the model
                $model->id = $record->id;

				MarketDbHelper::commitStackedTransaction();
			} catch (\Exception $e) {
				MarketDbHelper::rollbackStackedTransaction();
				throw $e;
			}

			return true;
		} else {
			return false;
		}
	}

	public function deleteById($id)
	{
        Market_OrderStatusRecord::model()->deleteByPk($id);
	}

}