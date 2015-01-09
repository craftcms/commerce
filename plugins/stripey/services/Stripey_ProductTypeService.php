<?php

namespace Craft;


class Stripey_ProductTypeService extends BaseApplicationComponent
{
    public function getAllProductTypes()
    {
        $productTypeRecords = Stripey_ProductTypeRecord::model()->findAll();
        return Stripey_ProductTypeModel::populateModels($productTypeRecords);
    }

    public function getProductTypeById($id)
    {
        $productTypeRecord = Stripey_ProductTypeRecord::model()->findById($id);
        return Stripey_ProductTypeModel::populateModel($productTypeRecord);
    }

    public function getProductTypeByHandle($handle)
    {
        $productTypeRecord = Stripey_ProductTypeRecord::model()->findByAttributes(array(
            'handle' => $handle
        ));
        return Stripey_ProductTypeModel::populateModel($productTypeRecord);
    }


    public function saveProductType(Stripey_ProductTypeModel $productType)
    {
        if ($productType->id)
        {
            $productTypeRecord = Stripey_ProductTypeRecord::model()->findById($productType->id);

            if (!$productTypeRecord)
            {
                throw new Exception(Craft::t('No calendar exists with the ID “{id}”', array('id' => $productType->id)));
            }

            $oldProductType = Stripey_ProductTypeModel::populateModel($productTypeRecord);
            $isNewProductType = false;
        }
        else
        {
            $productTypeRecord = new Stripey_ProductTypeRecord();
            $isNewProductType = true;
        }

        $productTypeRecord->name       = $productType->name;
        $productTypeRecord->handle     = $productType->handle;

        $productTypeRecord->validate();
        $productType->addErrors($productTypeRecord->getErrors());

        if (!$productType->hasErrors())
        {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try
            {
                if (!$isNewProductType && $oldProductType->fieldLayoutId)
                {
                    // Drop the old field layout
                    craft()->fields->deleteLayoutById($oldProductType->fieldLayoutId);
                }

                // Save the new one
                $fieldLayout = $productType->getFieldLayout();
                craft()->fields->saveLayout($fieldLayout);

                // Update the calendar record/model with the new layout ID
                $productType->fieldLayoutId = $fieldLayout->id;
                $productTypeRecord->fieldLayoutId = $fieldLayout->id;

                // Save it!
                $productTypeRecord->save(false);

                // Now that we have a calendar ID, save it on the model
                if (!$productType->id)
                {
                    $productType->id = $productTypeRecord->id;
                }

                if ($transaction !== null)
                {
                    $transaction->commit();
                }
            }
            catch (\Exception $e)
            {
                if ($transaction !== null)
                {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    public function deleteProductTypeById($id)
    {
        $productType = Stripey_ProductTypeRecord::model()->findById($id);
        $productType->delete();
    }


}