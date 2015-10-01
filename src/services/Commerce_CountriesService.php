<?php
namespace Craft;

/**
 * Country service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_CountriesService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_CountryModel
	 */
	public function getById ($id)
	{
		$record = Commerce_CountryRecord::model()->findById($id);

		return Commerce_CountryModel::populateModel($record);
	}

	/**
	 * @param array $attr
	 *
	 * @return Commerce_CountryModel
	 */
	public function getByAttributes (array $attr)
	{
		$record = Commerce_CountryRecord::model()->findByAttributes($attr);

		return Commerce_CountryModel::populateModel($record);
	}

	/**
	 * Simple list for using in forms
	 *
	 * @return array [id => name]
	 */
	public function getFormList ()
	{
		$countries = $this->getAll();

		return \CHtml::listData($countries, 'id', 'name');
	}

	/**
	 * @return Commerce_CountryModel[]
	 */
	public function getAll ()
	{
		$records = Commerce_CountryRecord::model()->findAll(['order' => 'name']);

		return Commerce_CountryModel::populateModels($records);
	}

	/**
	 * @param Commerce_CountryModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save (Commerce_CountryModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_CountryRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No country exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_CountryRecord();
		}

		$record->name = $model->name;
		$record->iso = strtoupper($model->iso);
		$record->stateRequired = $model->stateRequired;

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
		Commerce_CountryRecord::model()->deleteByPk($id);
	}
}