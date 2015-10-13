<?php
namespace Craft;

/**
 * Tax category service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_TaxCategoriesService extends BaseApplicationComponent
{
    /**
     * @return Commerce_TaxCategoryModel[]
     */
    public function getAll()
    {
        $records = Commerce_TaxCategoryRecord::model()->findAll();

        return Commerce_TaxCategoryModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Commerce_TaxCategoryModel
     */
    public function getById($id)
    {
        $record = Commerce_TaxCategoryRecord::model()->findById($id);

        return Commerce_TaxCategoryModel::populateModel($record);
    }

    /**
     * Id of default tax category
     *
     * @return int|null
     */
    public function getDefaultId()
    {
        $default = Commerce_TaxCategoryRecord::model()->findByAttributes(['default' => true]);

        return $default ? $default->id : null;
    }

    /**
     * @param Commerce_TaxCategoryModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(Commerce_TaxCategoryModel $model)
    {
        if ($model->id) {
            $record = Commerce_TaxCategoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_TaxCategoryRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->description = $model->description;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            //If this was the default make all others not the default.
            if ($model->default) {
                Commerce_TaxCategoryRecord::model()->updateAll(['default' => 0],
                    'id != ?', [$record->id]);
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
        Commerce_TaxCategoryRecord::model()->deleteByPk($id);
    }
}