<?php

namespace Craft;

/**
 * Class Stripey_ProductTypeService
 *
 * @package Craft
 */
class Stripey_ProductTypeService extends BaseApplicationComponent
{
	/**
	 * @return Stripey_ProductTypeModel[]
	 */
	public function getAll()
	{
		$productTypeRecords = Stripey_ProductTypeRecord::model()->findAll();

		return Stripey_ProductTypeModel::populateModels($productTypeRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Stripey_ProductTypeModel
	 */
	public function getById($id)
	{
		$productTypeRecord = Stripey_ProductTypeRecord::model()->findById($id);

		return Stripey_ProductTypeModel::populateModel($productTypeRecord);
	}

	/**
	 * @param string $handle
	 *
	 * @return Stripey_ProductTypeModel
	 */
	public function getByHandle($handle)
	{
		$productTypeRecord = Stripey_ProductTypeRecord::model()->findByAttributes(array('handle' => $handle));

		return Stripey_ProductTypeModel::populateModel($productTypeRecord);
	}

	/**
	 * @param Stripey_ProductTypeModel $productType
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Stripey_ProductTypeModel $productType)
	{
		if ($productType->id) {
			$productTypeRecord = Stripey_ProductTypeRecord::model()->findById($productType->id);
			if (!$productTypeRecord) {
				throw new Exception(Craft::t('No product type exists with the ID “{id}”', array('id' => $productType->id)));
			}

			$oldProductType   = Stripey_ProductTypeModel::populateModel($productTypeRecord);
			$isNewProductType = false;
		} else {
			$productTypeRecord = new Stripey_ProductTypeRecord();
			$isNewProductType  = true;
		}

		$productTypeRecord->name   = $productType->name;
		$productTypeRecord->handle = $productType->handle;

		$productTypeRecord->validate();
		$productType->addErrors($productTypeRecord->getErrors());

		if (!$productType->hasErrors()) {
			$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
			try {
				if (!$isNewProductType && $oldProductType->fieldLayoutId) {
					// Drop the old field layout
					craft()->fields->deleteLayoutById($oldProductType->fieldLayoutId);
				}

				// Save the new one
				$fieldLayout = $productType->getFieldLayout();
				craft()->fields->saveLayout($fieldLayout);

				// Update the calendar record/model with the new layout ID
				$productType->fieldLayoutId       = $fieldLayout->id;
				$productTypeRecord->fieldLayoutId = $fieldLayout->id;

				// Save it!
				$productTypeRecord->save(false);

				// Now that we have a calendar ID, save it on the model
				if (!$productType->id) {
					$productType->id = $productTypeRecord->id;
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

	/**
	 * @param int $id
	 *
	 * @throws \CDbException
	 */
	public function deleteById($id)
	{
		$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
		try {
			$productType = Stripey_ProductTypeRecord::model()->findById($id);

			$query      = craft()->db->createCommand()
				->select('id')
				->from('stripey_products')
				->where(array('typeId' => $productType->id));
			$productIds = $query->queryColumn();

			craft()->elements->deleteElementById($productIds);
			craft()->fields->deleteLayoutById($productType->fieldLayoutId);

			$affectedRows = $productType->delete();

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