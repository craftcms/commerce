<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_OrderTypeService
 *
 * @package Craft
 */
class Market_OrderTypeService extends BaseApplicationComponent
{
	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Market_OrderTypeModel[]
	 */
	public function getAll($criteria = [])
	{
		$orderTypeRecords = Market_OrderTypeRecord::model()->findAll($criteria);

		return Market_OrderTypeModel::populateModels($orderTypeRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_OrderTypeModel
	 */
	public function getById($id)
	{
		$orderTypeRecord = Market_OrderTypeRecord::model()->findById($id);

		return Market_OrderTypeModel::populateModel($orderTypeRecord);
	}

	/**
	 * @param string $handle
	 *
	 * @return Market_OrderTypeModel
	 */
	public function getByHandle($handle)
	{
		$orderTypeRecord = Market_OrderTypeRecord::model()->findByAttributes(['handle' => $handle]);

		return Market_OrderTypeModel::populateModel($orderTypeRecord);
	}

	/**
	 * Get first (default) order type from the DB
	 *
	 * @return Market_OrderTypeModel
	 */
	public function getFirst()
	{
		$orderType = Market_OrderTypeRecord::model()->find(['order' => 'id', 'limit' => 1]);

		return Market_OrderTypeModel::populateModel($orderType);
	}

	/**
	 * @param Market_OrderTypeModel $orderType
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_OrderTypeModel $orderType)
	{
		if ($orderType->id) {
			$orderTypeRecord = Market_OrderTypeRecord::model()->findById($orderType->id);
			if (!$orderTypeRecord) {
				throw new Exception(Craft::t('No order type exists with the ID “{id}”', ['id' => $orderType->id]));
			}

			$oldOrderType   = Market_OrderTypeModel::populateModel($orderTypeRecord);
			$isNewOrderType = false;
		} else {
			$orderTypeRecord = new Market_OrderTypeRecord();
			$isNewOrderType  = true;
		}

		$orderTypeRecord->name             = $orderType->name;
		$orderTypeRecord->handle           = $orderType->handle;
		$orderTypeRecord->shippingMethodId = $orderType->shippingMethodId;

		$orderTypeRecord->validate();
		$orderType->addErrors($orderTypeRecord->getErrors());

		if (!$orderType->hasErrors()) {
			$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
			try {
				if (!$isNewOrderType && $oldOrderType->fieldLayoutId) {
					// Drop the old field layout
					craft()->fields->deleteLayoutById($oldOrderType->fieldLayoutId);
				}

				// Save the new one
				$fieldLayout = $orderType->getFieldLayout();
				craft()->fields->saveLayout($fieldLayout);

				// Update the calendar record/model with the new layout ID
				$orderType->fieldLayoutId       = $fieldLayout->id;
				$orderTypeRecord->fieldLayoutId = $fieldLayout->id;

				// Save it!
				$orderTypeRecord->save(false);

				// Now that we have a calendar ID, save it on the model
				if (!$orderType->id) {
					$orderType->id = $orderTypeRecord->id;
				}

				if ($transaction !== NULL) {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				if ($transaction !== NULL) {
					$transaction->rollback();
				}

				throw $e;
			}

			return true;
		} else {
			return false;
		}
	}

	public function deleteById($id)
	{
		MarketDbHelper::beginStackedTransaction();
		try {
			$orderType = Market_OrderTypeRecord::model()->findById($id);

			$query    = craft()->db->createCommand()
				->select('id')
				->from('market_orders')
				->where(['typeId' => $orderType->id]);
			$orderIds = $query->queryColumn();

			craft()->elements->deleteElementById($orderIds);
			craft()->fields->deleteLayoutById($orderType->fieldLayoutId);
			Market_OptionValueRecord::model()->deleteAllByAttributes(['optionTypeId' => $orderType->id]);

			$affectedRows = $orderType->delete();

			MarketDbHelper::commitStackedTransaction();

			return (bool)$affectedRows;
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}
	}

}