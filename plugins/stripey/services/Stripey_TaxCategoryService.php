<?php

namespace Craft;

/**
 * Class Stripey_TaxCategoryService
 * @package Craft
 */
class Stripey_TaxCategoryService extends BaseApplicationComponent
{
    /**
     * @return Stripey_TaxCategoryModel[]
     */
    public function getAllTaxCategories()
    {
        $records = Stripey_TaxCategoryRecord::model()->findAll();
        return Stripey_TaxCategoryModel::populateModels($records);
    }

    /**
     * @param int $id
     * @return Stripey_TaxCategoryModel
     */
    public function getTaxCategoryById($id)
    {
        $record = Stripey_TaxCategoryRecord::model()->findById($id);
        return Stripey_TaxCategoryModel::populateModel($record);
    }

    /**
     * @param Stripey_TaxCategoryModel $model
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */    
    public function save(Stripey_TaxCategoryModel $model)
    {
        if ($model->id) {
            $record = Stripey_TaxCategoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax category exists with the ID “{id}”', array('id' => $model->id)));
            }
        } else {
            $record = new Stripey_TaxCategoryRecord();
        }

        $record->name = $model->name;
        $record->code = $model->code;
        $record->description = $model->description;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try {
                // Save it!
                $record->save(false);

                // Now that we have a calendar ID, save it on the model
                if (!$model->id) {
                    $model->id = $record->id;
                }

                if ($transaction !== null) {
                    $transaction->commit();
                }
            } catch (\Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     * @throws \CDbException
     */
    public function deleteById($id)
    {
        $taxCategory = Stripey_TaxCategoryRecord::model()->findById($id);
        $taxCategory->delete();
    }
}