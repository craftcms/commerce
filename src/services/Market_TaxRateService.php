<?php

namespace Craft;

/**
 * Class Market_TaxRateService
 *
 * @package Craft
 */
class Market_TaxRateService extends BaseApplicationComponent
{
	/**
	 * @return Market_TaxRateModel[]
	 */
	public function getAll()
	{
		$records = Market_TaxRateRecord::model()->with(array('taxZone', 'taxCategory'))->findAll(array('order' => 't.name'));

		return Market_TaxRateModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_TaxRateModel
	 */
	public function getById($id)
	{
		$record = Market_TaxRateRecord::model()->findById($id);

		return Market_TaxRateModel::populateModel($record);
	}

	/**
	 * @param Market_TaxRateModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_TaxRateModel $model)
	{
		if ($model->id) {
			$record = Market_TaxRateRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No tax rate exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Market_TaxRateRecord();
		}

		$record->name          = $model->name;
		$record->rate          = $model->rate;
		$record->include       = $model->include;
		$record->showInLabel   = $model->showInLabel;
		$record->taxCategoryId = $model->taxCategoryId;
		$record->taxZoneId     = $model->taxZoneId;

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
		$TaxRate = Market_TaxRateRecord::model()->findById($id);
		$TaxRate->delete();
	}
}