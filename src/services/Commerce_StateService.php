<?php
namespace Craft;

/**
 * Class Commerce_StateService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_StateService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_StateModel
	 */
	public function getById ($id)
	{
		$record = Commerce_StateRecord::model()->findById($id);

		return Commerce_StateModel::populateModel($record);
	}

	/**
	 * @param array $attr
	 *
	 * @return Commerce_StateModel
	 */
	public function getByAttributes (array $attr)
	{
		$record = Commerce_StateRecord::model()->findByAttributes($attr);

		return Commerce_StateModel::populateModel($record);
	}

	/**
	 * @return array [countryId => [stateId => stateName]]
	 */
	public function getGroupedByCountries ()
	{
		$states = craft()->commerce_state->getAll();
		$cid2state = [];

		foreach ($states as $state)
		{
			$cid2state += [$state->countryId => []];
			$cid2state[$state->countryId][$state->id] = $state->name;
		}

		return $cid2state;
	}

	/**
	 * @return Commerce_StateModel[]
	 */
	public function getAll ()
	{
		$records = Commerce_StateRecord::model()->with('country')->findAll(['order' => 'country.name, t.name']);

		return Commerce_StateModel::populateModels($records);
	}

	/**
	 * @param Commerce_StateModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save (Commerce_StateModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_StateRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No state exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_StateRecord();
		}

		$record->name = $model->name;
		$record->abbreviation = $model->abbreviation;
		$record->countryId = $model->countryId;

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			// Save it!
			$record->save(false);

			// Now that we have a record ID, save it on the model
			$model->id = $record->id;

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @throws \CDbException
	 */
	public function deleteById ($id)
	{
		$State = Commerce_StateRecord::model()->findById($id);
		$State->delete();
	}
}