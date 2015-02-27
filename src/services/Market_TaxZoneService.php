<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_TaxZoneService
 *
 * @package Craft
 */
class Market_TaxZoneService extends BaseApplicationComponent
{
	/**
	 * @param bool $withRelations
	 *
	 * @return Market_TaxZoneModel[]
	 */
	public function getAll($withRelations = true)
	{
		$with    = $withRelations ? array('countries', 'states', 'states.country') : array();
		$records = Market_TaxZoneRecord::model()->with($with)->findAll(array('order' => 't.name'));

		return Market_TaxZoneModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_TaxZoneModel
	 */
	public function getById($id)
	{
		$record = Market_TaxZoneRecord::model()->findById($id);

		return Market_TaxZoneModel::populateModel($record);
	}

	/**
	 * @param Market_TaxZoneModel $model
	 * @param array               $countriesIds
	 * @param array               $statesIds
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save(Market_TaxZoneModel $model, $countriesIds, $statesIds)
	{
		if ($model->id) {
			$record = Market_TaxZoneRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No tax zone exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_TaxZoneRecord();
		}

		//remembering which links should be clean
		$deleteOldCountries = $deleteOldStates = false;
		if ($record->id) {
			if ($record->countryBased) {
				$deleteOldCountries = true;
			} else {
				$deleteOldStates = true;
			}
		}

		//setting attributes
		$record->name         = $model->name;
		$record->description  = $model->description;
		$record->countryBased = $model->countryBased;
		$record->default      = $model->default;

		$record->validate();
		$model->addErrors($record->getErrors());

		//validating given ids
		if ($record->countryBased) {
			$criteria = new \CDbCriteria();
			$criteria->addInCondition('id', $countriesIds);
			$exist = Market_CountryRecord::model()->exists($criteria);

			if (!$exist) {
				$model->addError('countries', 'Please select some countries');
			}
		} else {
			$criteria = new \CDbCriteria();
			$criteria->addInCondition('id', $statesIds);
			$exist = Market_StateRecord::model()->exists($criteria);

			if (!$exist) {
				$model->addError('states', 'Please select some states');
			}
		}

		//saving
		if (!$model->hasErrors()) {
			MarketDbHelper::beginStackedTransaction();
			try {
				// Save it!
				$record->save(false);

				// Now that we have a record ID, save it on the model
				$model->id = $record->id;

				//deleting old links
				if ($deleteOldCountries) {
					Market_TaxZoneCountryRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);
				}

				if ($deleteOldStates) {
					Market_TaxZoneStateRecord::model()->deleteAllByAttributes(['taxZoneId' => $record->id]);
				}

				//saving new links
				if ($model->countryBased) {
					$rows  = array_map(function ($id) use ($model) {
						return [$id, $model->id];
					}, $countriesIds);
					$cols  = ['countryId', 'taxZoneId'];
					$table = Market_TaxZoneCountryRecord::model()->getTableName();
				} else {
					$rows  = array_map(function ($id) use ($model) {
						return [$id, $model->id];
					}, $statesIds);
					$cols  = ['stateId', 'taxZoneId'];
					$table = Market_TaxZoneStateRecord::model()->getTableName();
				}
				craft()->db->createCommand()->insertAll($table, $cols, $rows);

				//If this was the default make all others not the default.
				if ($model->default) {
					Market_TaxZoneRecord::model()->updateAll(['default' => 0], 'id != ?', [$record->id]);
				}

				MarketDbHelper::commitStackedTransaction();
			} catch (\Exception $e) {
				MarketDbHelper::rollbackStackedTransaction();

				throw $e;
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param int $id
	 */
	public function deleteById($id)
	{
		Market_TaxZoneRecord::model()->deleteByPk($id);
	}
}