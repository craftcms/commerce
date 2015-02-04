<?php

namespace Craft;

/**
 * Class Stripey_OrderTypeService
 *
 * @package Craft
 */
class Stripey_OrderTypeService extends BaseApplicationComponent
{
	/**
	 * @return Stripey_OrderTypeModel[]
	 */
	public function getAll()
	{
		$ordeTypeRecords = Stripey_OrderTypeRecord::model()->findAll();

		return Stripey_OrderTypeModel::populateModels($ordeTypeRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Stripey_OrderTypeModel
	 */
	public function getById($id)
	{
		$ordeTypeRecord = Stripey_OrderTypeRecord::model()->findById($id);

		return Stripey_OrderTypeModel::populateModel($ordeTypeRecord);
	}

	/**
	 * @param string $handle
	 *
	 * @return Stripey_OrderTypeModel
	 */
	public function getByHandle($handle)
	{
		$ordeTypeRecord = Stripey_OrderTypeRecord::model()->findByAttributes(array('handle' => $handle));

		return Stripey_OrderTypeModel::populateModel($ordeTypeRecord);
	}

	/**
	 * @param Stripey_OrderTypeModel $ordeType
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Stripey_OrderTypeModel $ordeType)
	{
		if ($ordeType->id) {
			$ordeTypeRecord = Stripey_OrderTypeRecord::model()->findById($ordeType->id);
			if (!$ordeTypeRecord) {
				throw new Exception(Craft::t('No orde type exists with the ID “{id}”', array('id' => $ordeType->id)));
			}

			$oldOrderType   = Stripey_OrderTypeModel::populateModel($ordeTypeRecord);
			$isNewOrderType = false;
		} else {
			$ordeTypeRecord = new Stripey_OrderTypeRecord();
			$isNewOrderType = true;
		}

		$ordeTypeRecord->name   = $ordeType->name;
		$ordeTypeRecord->handle = $ordeType->handle;

		$ordeTypeRecord->validate();
		$ordeType->addErrors($ordeTypeRecord->getErrors());

		if (!$ordeType->hasErrors()) {
			$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
			try {
				if (!$isNewOrderType && $oldOrderType->fieldLayoutId) {
					// Drop the old field layout
					craft()->fields->deleteLayoutById($oldOrderType->fieldLayoutId);
				}

				// Save the new one
				$fieldLayout = $ordeType->getFieldLayout();
				craft()->fields->saveLayout($fieldLayout);

				// Update the calendar record/model with the new layout ID
				$ordeType->fieldLayoutId       = $fieldLayout->id;
				$ordeTypeRecord->fieldLayoutId = $fieldLayout->id;

				// Save it!
				$ordeTypeRecord->save(false);

				// Now that we have a calendar ID, save it on the model
				if (!$ordeType->id) {
					$ordeType->id = $ordeTypeRecord->id;
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
		$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
		try {
			$orderType = Stripey_OrderTypeRecord::model()->findById($id);

			$query    = craft()->db->createCommand()
				->select('id')
				->from('stripey_orders')
				->where(array('typeId' => $orderType->id));
			$orderIds = $query->queryColumn();

			craft()->elements->deleteElementById($orderIds);
			craft()->fields->deleteLayoutById($orderType->fieldLayoutId);

			$affectedRows = $orderType->delete();

			if ($transaction !== NULL) {
				$transaction->commit();
			}

			return (bool)$affectedRows;
		} catch (\Exception $e) {
			if ($transaction !== NULL) {
				$transaction->rollback();
			}

			throw $e;
		}
	}

}