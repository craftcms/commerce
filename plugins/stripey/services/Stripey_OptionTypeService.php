<?php

namespace Craft;

/**
 * Class Stripey_OptionTypeService
 *
 * @package Craft
 */
class Stripey_OptionTypeService extends BaseApplicationComponent
{
	/**
	 * @return Stripey_OptionTypeModel[]
	 */
	public function getAll()
	{
		$optionTypeRecords = Stripey_OptionTypeRecord::model()->findAll();

		return Stripey_OptionTypeModel::populateModels($optionTypeRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Stripey_OptionTypeModel
	 */
	public function getById($id)
	{
		$optionTypeRecord = Stripey_OptionTypeRecord::model()->findById($id);

		return Stripey_OptionTypeModel::populateModel($optionTypeRecord);
	}

	/**
	 * @param $handle
	 *
	 * @return Stripey_OptionTypeModel
	 */
	public function getByHandle($handle)
	{
		$optionTypeRecord = Stripey_OptionTypeRecord::model()->findByAttributes(array('handle' => $handle));

		return Stripey_OptionTypeModel::populateModel($optionTypeRecord);
	}

	/**
	 * @param Stripey_OptionTypeModel $optionType
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Stripey_OptionTypeModel $optionType)
	{
		if ($optionType->id) {
			$record = Stripey_OptionTypeRecord::model()->findById($optionType->id);

			if (!$record) {
				throw new Exception(Craft::t('No option type exists with the ID “{id}”', array('id' => $optionType->id)));
			}
		} else {
			$record = new Stripey_OptionTypeRecord();
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
		Stripey_OptionTypeRecord::model()->deleteByPk($id);
	}

}