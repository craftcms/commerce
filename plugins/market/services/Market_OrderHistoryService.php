<?php

namespace Craft;

/**
 * Class Market_OrderHistoryService
 *
 * @package Craft
 */
class Market_OrderHistoryService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @return Market_OrderHistoryModel
	 */
	public function getById($id)
	{
		$record = Market_OrderHistoryRecord::model()->findById($id);
		return Market_OrderHistoryModel::populateModel($record);
	}

	/**
	 * @param array $attr
	 * @return Market_OrderHistoryModel
	 */
	public function getByAttributes(array $attr)
	{
		$record = Market_OrderHistoryRecord::model()->findByAttributes($attr);
		return Market_OrderHistoryModel::populateModel($record);
	}

    /**
     * @param \CDbCriteria|array $criteria
     * @return Market_OrderHistoryModel[]
     */
	public function getAll(array $criteria = [])
	{
		$records = Market_OrderHistoryRecord::model()->findAll($criteria);
		return Market_OrderHistoryModel::populateModels($records);
	}

	/**
	 * @param Market_OrderHistoryModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_OrderHistoryModel $model)
	{
		if ($model->id) {
			$record = Market_OrderHistoryRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No order history exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_OrderHistoryRecord();
		}

        $record->message      = $model->message;
        $record->newStatusId  = $model->newStatusId;
        $record->prevStatusId = $model->prevStatusId;
        $record->userId       = $model->userId;
        $record->orderId      = $model->orderId;

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors()) {
			// Save it!
			$record->save(false);

			// Now that we have a record ID, save it on the model
			$model->id = $record->id;

			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @throws \CDbException
	 */
	public function deleteById($id)
	{
        Market_OrderHistoryRecord::model()->deleteByPk($id);
	}
}