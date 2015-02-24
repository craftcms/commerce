<?php

namespace Craft;

/**
 * Class Market_ShippingMethodService
 *
 * @package Craft
 */
class Market_ShippingMethodService extends BaseApplicationComponent
{
    /**
     * @param array|\CDbCriteria $criteria
     * @return Market_ShippingMethodModel[]
     */
	public function getAll($criteria = [])
	{
		$records = Market_ShippingMethodRecord::model()->findAll($criteria);
		return Market_ShippingMethodModel::populateModels($records);
	}

	/**
	 * @param int $id
	 * @return Market_ShippingMethodModel
	 */
	public function getById($id)
	{
		$record = Market_ShippingMethodRecord::model()->findById($id);
		return Market_ShippingMethodModel::populateModel($record);
	}

	/**
	 * @param Market_ShippingMethodModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_ShippingMethodModel $model)
	{
		if ($model->id) {
			$record = Market_ShippingMethodRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No shipping method exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_ShippingMethodRecord();
		}

		$record->name    = $model->name;
		$record->enabled = $model->enabled;

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
		$ShippingMethod = Market_ShippingMethodRecord::model()->findById($id);
		$ShippingMethod->delete();
	}
}