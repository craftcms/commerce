<?php

namespace Craft;

/**
 * Class Market_TaxCategoryService
 *
 * @package Craft
 */
class Market_TaxCategoryService extends BaseApplicationComponent
{
	/**
	 * @return Market_TaxCategoryModel[]
	 */
	public function getAll()
	{
		$records = Market_TaxCategoryRecord::model()->findAll();

		return Market_TaxCategoryModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_TaxCategoryModel
	 */
	public function getById($id)
	{
		$record = Market_TaxCategoryRecord::model()->findById($id);

		return Market_TaxCategoryModel::populateModel($record);
	}

	/**
	 * @param Market_TaxCategoryModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_TaxCategoryModel $model)
	{
		if ($model->id) {
			$record = Market_TaxCategoryRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No tax category exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Market_TaxCategoryRecord();
		}

		$record->name        = $model->name;
		$record->code        = $model->code;
		$record->description = $model->description;
		$record->default     = $model->default;

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
		$taxCategory = Market_TaxCategoryRecord::model()->findById($id);
		$taxCategory->delete();
	}
}