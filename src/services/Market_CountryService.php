<?php

namespace Craft;

/**
 * Class Market_CountryService
 *
 * @package Craft
 */
class Market_CountryService extends BaseApplicationComponent
{
	/**
	 * @return Market_CountryModel[]
	 */
	public function getAll()
	{
		$records = Market_CountryRecord::model()->findAll(array('order' => 'name'));

		return Market_CountryModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_CountryModel
	 */
	public function getById($id)
	{
		$record = Market_CountryRecord::model()->findById($id);

		return Market_CountryModel::populateModel($record);
	}

    /**
     * @param array $attr
     * @return Market_CountryModel
     */
    public function getByAttributes(array $attr)
    {
        $record = Market_CountryRecord::model()->findByAttributes($attr);
        return Market_CountryModel::populateModel($record);
    }

	/**
	 * Simple list for using in forms
	 * @return array [id => name]
	 */
	public function getFormList()
	{
		$countries = $this->getAll();
		return \CHtml::listData($countries, 'id', 'name');
	}

	/**
	 * @param Market_CountryModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_CountryModel $model)
	{
		if ($model->id) {
			$record = Market_CountryRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No country exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Market_CountryRecord();
		}

		$record->name          = $model->name;
		$record->iso           = strtoupper($model->iso);
		$record->stateRequired = $model->stateRequired;

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
		$Country = Market_CountryRecord::model()->findById($id);
		$Country->delete();
	}
}