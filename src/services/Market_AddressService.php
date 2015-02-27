<?php

namespace Craft;

/**
 * Class Market_AddressService
 *
 * @package Craft
 */
class Market_AddressService extends BaseApplicationComponent
{
	/**
	 * @return Market_AddressModel[]
	 */
	public function getAll()
	{
		$records = Market_AddressRecord::model()->with('country', 'state')->findAll(['order' => 't.name']);

		return Market_AddressModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_AddressModel
	 */
	public function getById($id)
	{
		$record = Market_AddressRecord::model()->findById($id);

		return Market_AddressModel::populateModel($record);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_AddressModel[]
	 */
	public function getByCustomerId($id)
	{
		$addresses = Market_AddressRecord::model()->findAll([
			'join'      => 'JOIN craft_market_customer_addresses cmca ON cmca.addressId = t.id',
			'condition' => 'cmca.customerId = :id',
			'params'    => ['id' => $id],
		]);

		return Market_AddressModel::populateModels($addresses);
	}

	/**
	 * @param Market_AddressModel $model
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save(Market_AddressModel $model)
	{
		if ($model->id) {
			$record = Market_AddressRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No address exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_AddressRecord();
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

		if (!empty($model->stateValue)) {
			if (is_numeric($model->stateValue)) {
				$record->stateId = $model->stateId = $model->stateValue;
			} else {
				$record->stateName = $model->stateName = $model->stateValue;
			}
		} else {
			$record->stateId   = $model->stateId;
			$record->stateName = $model->stateName;
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
		$Address = Market_AddressRecord::model()->findById($id);
		$Address->delete();
	}
}