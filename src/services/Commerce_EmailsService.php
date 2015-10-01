<?php
namespace Craft;

/**
 * Email service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_EmailsService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_EmailModel
	 */
	public function getById ($id)
	{
		$record = Commerce_EmailRecord::model()->findById($id);

		return Commerce_EmailModel::populateModel($record);
	}

	/**
	 * @param array $attr
	 *
	 * @return Commerce_EmailModel
	 */
	public function getByAttributes (array $attr)
	{
		$record = Commerce_EmailRecord::model()->findByAttributes($attr);

		return Commerce_EmailModel::populateModel($record);
	}

	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Commerce_EmailModel[]
	 */
	public function getAll ($criteria = [])
	{
		$records = Commerce_EmailRecord::model()->findAll($criteria);

		return Commerce_EmailModel::populateModels($records);
	}

	/**
	 * @param Commerce_EmailModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save (Commerce_EmailModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_EmailRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No email exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_EmailRecord();
		}

		$record->name = $model->name;
		$record->subject = $model->subject;
		$record->to = $model->to;
		$record->bcc = $model->bcc;
		$record->enabled = $model->enabled;
		$record->templatePath = $model->templatePath;

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
		Commerce_EmailRecord::model()->deleteByPk($id);
	}
}