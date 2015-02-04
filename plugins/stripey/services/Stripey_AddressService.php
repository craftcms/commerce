<?php

namespace Craft;

/**
 * Class Stripey_AddressService
 *
 * @package Craft
 */
class Stripey_AddressService extends BaseApplicationComponent
{
	/**
	 * @return Stripey_AddressModel[]
	 */
	public function getAll()
	{
		$records = Stripey_AddressRecord::model()->with('country', 'state')->findAll(array('order' => 't.name'));

		return Stripey_AddressModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Stripey_AddressModel
	 */
	public function getById($id)
	{
		$record = Stripey_AddressRecord::model()->findById($id);

		return Stripey_AddressModel::populateModel($record);
	}

	/**
	 * @param Stripey_AddressModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Stripey_AddressModel $model)
	{
		if ($model->id) {
			$record = Stripey_AddressRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No address exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Stripey_AddressRecord();
		}

		$record->firstName        = $model->firstName;
		$record->lastName         = $model->lastName;
		$record->address1         = $model->address1;
		$record->address2         = $model->address2;
		$record->zipCode          = $model->zipCode;
		$record->phone            = $model->phone;
		$record->alternativePhone = $model->alternativePhone;
		$record->company          = $model->company;
		$record->countryId        = $model->countryId;

		if (is_numeric($model->stateValue)) {
			$record->stateId = $model->stateId = $model->stateValue;
		} else {
			$record->stateName = $model->stateName = $model->stateValue;
		}

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
		$Address = Stripey_AddressRecord::model()->findById($id);
		$Address->delete();
	}
}