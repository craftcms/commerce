<?php

namespace Craft;

/**
 * Class Market_StateService
 *
 * @package Craft
 */
class Market_StateService extends BaseApplicationComponent
{
	/**
	 * @return Market_StateModel[]
	 */
	public function getAll()
	{
		$records = Market_StateRecord::model()->with('country')->findAll(array('order' => 'country.name, t.name'));

		return Market_StateModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_StateModel
	 */
	public function getById($id)
	{
		$record = Market_StateRecord::model()->findById($id);

		return Market_StateModel::populateModel($record);
	}

	/**
	 * @return array [countryId => [stateId => stateName]]
	 */
	public function getGroupedByCountries()
	{
		$states = craft()->market_state->getAll();
		$cid2state = [];

		foreach($states as $state) {
			$cid2state += [$state->countryId => []];
			$cid2state[$state->countryId][$state->id] = $state->name;
		}

		return $cid2state;
	}

	/**
	 * @param Market_StateModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_StateModel $model)
	{
		if ($model->id) {
			$record = Market_StateRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No state exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Market_StateRecord();
		}

		$record->name         = $model->name;
		$record->abbreviation = $model->abbreviation;
		$record->countryId    = $model->countryId;

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
		$State = Market_StateRecord::model()->findById($id);
		$State->delete();
	}
}