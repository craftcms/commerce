<?php

namespace Craft;

/**
 * Class Market_OptionTypeService
 *
 * @package Craft
 */
class Market_OptionTypeService extends BaseApplicationComponent
{
	/**
	 * @return Market_OptionTypeModel[]
	 */
	public function getAll()
	{
		$optionTypeRecords = Market_OptionTypeRecord::model()->findAll();

		return Market_OptionTypeModel::populateModels($optionTypeRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_OptionTypeModel
	 */
	public function getById($id)
	{
		$optionTypeRecord = Market_OptionTypeRecord::model()->findById($id);

		return Market_OptionTypeModel::populateModel($optionTypeRecord);
	}

	/**
	 * @param $handle
	 *
	 * @return Market_OptionTypeModel
	 */
	public function getByHandle($handle)
	{
		$optionTypeRecord = Market_OptionTypeRecord::model()->findByAttributes(['handle' => $handle]);

		return Market_OptionTypeModel::populateModel($optionTypeRecord);
	}

	/**
	 * @param Market_OptionTypeModel $optionType
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_OptionTypeModel $optionType)
	{
		if ($optionType->id) {
			$record = Market_OptionTypeRecord::model()->findById($optionType->id);

			if (!$record) {
				throw new Exception(Craft::t('No option type exists with the ID “{id}”', ['id' => $optionType->id]));
			}
		} else {
			$record = new Market_OptionTypeRecord();
		}

		$record->name   = $optionType->name;
		$record->handle = $optionType->handle;

		$record->validate();
		$optionType->addErrors($record->getErrors());

		if (!$optionType->hasErrors()) {
			// Save it!
			$record->save(false);

			// Now that we have a optionType ID, save it on the model
			$optionType->id = $record->id;

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
		Market_OptionTypeRecord::model()->deleteByPk($id);
	}

}