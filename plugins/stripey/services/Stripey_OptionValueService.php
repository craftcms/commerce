<?php

namespace Craft;


class Stripey_OptionValueService extends BaseApplicationComponent
{
    public function getAllOptionValuesByOptionTypeId($id)
    {
        $find = array('optionTypeId'=>$id);
        $optionValueRecords = Stripey_OptionValueRecord::model()->findByAttributes($find);
        return Stripey_OptionValueModel::populateModels($optionValueRecords);
    }

    public function getOptionValueById($id)
    {
        $optionValueRecord = Stripey_OptionValueRecord::model()->findById($id);
        return Stripey_OptionValueModel::populateModel($optionValueRecord);
    }

    public function saveOptionValue(Stripey_OptionValueModel $optionValue)
    {
        if ($optionValue->id)
        {
            $optionValueRecord = Stripey_OptionValueRecord::model()->findById($optionValue->id);

            if (!$optionValueRecord)
            {
                throw new Exception(Craft::t('No Option Value exists with the ID “{id}”', array('id' => $optionValue->id)));
            }

            $oldOptionValue = Stripey_OptionValueModel::populateModel($optionValueRecord);
            $isNewOptionValue = false;
        }
        else
        {
            $optionValueRecord = new Stripey_OptionValueRecord();
            $isNewOptionValue = true;
        }

        $optionValueRecord->name       = $optionValue->name;
        $optionValueRecord->handle     = $optionValue->handle;

        $optionValueRecord->validate();
        $optionValue->addErrors($optionValueRecord->getErrors());

        if (!$optionValue->hasErrors())
        {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try
            {
                // Save it!
                $optionValueRecord->save(false);

                // Now that we have a optionType ID, save it on the model
                if (!$optionValue->id)
                {
                    $optionValue->id = $optionValueRecord->id;
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

    public function deleteOptionTypeById($id)
    {
        $optionType = Stripey_OptionTypeRecord::model()->findById($id);
        $optionType->delete();
    }


}