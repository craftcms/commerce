<?php
namespace Craft;

/**
 * Class Market_TaxCategoryService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_TaxCategoryService extends BaseApplicationComponent
{
	/**
	 * @return Market_TaxCategoryModel[]
	 */
	public function getAll ()
	{
		$records = Market_TaxCategoryRecord::model()->findAll();

		return Market_TaxCategoryModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_TaxCategoryModel
	 */
	public function getById ($id)
	{
		$record = Market_TaxCategoryRecord::model()->findById($id);

		return Market_TaxCategoryModel::populateModel($record);
	}

	/**
	 * Id of default tax category
	 *
	 * @return int|null
	 */
	public function getDefaultId ()
	{
		$default = Market_TaxCategoryRecord::model()->findByAttributes(['default' => true]);

		return $default ? $default->id : null;
	}

	/**
	 * @param Market_TaxCategoryModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save (Market_TaxCategoryModel $model)
	{
		if ($model->id)
		{
			$record = Market_TaxCategoryRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No tax category exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Market_TaxCategoryRecord();
		}

		$record->name = $model->name;
		$record->handle = $model->handle;
		$record->description = $model->description;
		$record->default = $model->default;

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			// Save it!
			$record->save(false);

			// Now that we have a record ID, save it on the model
			$model->id = $record->id;

			//If this was the default make all others not the default.
			if ($model->default)
			{
				Market_TaxCategoryRecord::model()->updateAll(['default' => 0],
					'id != ?', [$record->id]);
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param int $id
	 */
	public function deleteById ($id)
	{
		Market_TaxCategoryRecord::model()->deleteByPk($id);
	}
}