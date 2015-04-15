<?php

namespace Craft;

/**
 * Class Market_EmailService
 *
 * @package Craft
 */
class Market_EmailService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_EmailModel
	 */
	public function getById($id)
	{
		$record = Market_EmailRecord::model()->findById($id);

		return Market_EmailModel::populateModel($record);
	}

	/**
	 * @param array $attr
	 *
	 * @return Market_EmailModel
	 */
	public function getByAttributes(array $attr)
	{
		$record = Market_EmailRecord::model()->findByAttributes($attr);

		return Market_EmailModel::populateModel($record);
	}

	/**
     * @param array|\CDbCriteria $criteria
     * @return Market_EmailModel[]
	 */
    public function getAll($criteria = [])
	{
		$records = Market_EmailRecord::model()->findAll($criteria);
		return Market_EmailModel::populateModels($records);
	}

	/**
	 * @param Market_EmailModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_EmailModel $model)
	{
		if ($model->id) {
			$record = Market_EmailRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No email exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_EmailRecord();
		}

        $record->name         = $model->name;
        $record->subject      = $model->subject;
        $record->to           = $model->to;
        $record->bcc          = $model->bcc;
        $record->type         = $model->type;
        $record->enabled      = $model->enabled;
        $record->templatePath = $model->templatePath;

		$record->validate();
		$model->addErrors($record->getErrors());

        //validating template path
        if(!$model->getErrors('templatePath')) {
            $templatesDir = craft()->path->getSiteTemplatesPath();
            $fullPath = $templatesDir . $record->templatePath;
            if(!is_file($fullPath)) {
                $model->addError('templatePath', 'template not found');
            } elseif(preg_match('#(^|/)\.\./#', $record->templatePath)) { //checking occurences of "../" and "/../" in the path
                $model->addError('templatePath', 'relative paths are not allowed');
            }
        }

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
        Market_EmailRecord::model()->deleteByPk($id);
	}
}