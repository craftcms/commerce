<?php

namespace Craft;


class Stripey_OptionTypeService extends BaseApplicationComponent
{
    public function getAllOptionTypes()
    {
        $optionTypeRecords = Stripey_OptionTypeRecord::model()->findAll();

        return Stripey_OptionTypeModel::populateModels($optionTypeRecords);
    }

    public function getOptionTypeById($id)
    {
        $optionTypeRecord = Stripey_OptionTypeRecord::model()->findById($id);

        return Stripey_OptionTypeModel::populateModel($optionTypeRecord);
    }

    public function getOptionTypeByHandle($handle)
    {
        $optionTypeRecord = Stripey_OptionTypeRecord::model()->findByAttributes(array(
            'handle' => $handle
        ));

        return Stripey_OptionTypeModel::populateModel($optionTypeRecord);
    }

    public function saveOptionType(Stripey_OptionTypeModel $optionType)
    {
        if ($optionType->id) {
            $optionTypeRecord = Stripey_OptionTypeRecord::model()->findById($optionType->id);

            if (!$optionTypeRecord) {
                throw new Exception(Craft::t('No option type exists with the ID “{id}”', array('id' => $optionType->id)));
            }

            $oldOptionType   = Stripey_OptionTypeModel::populateModel($optionTypeRecord);
            $isNewOptionType = false;
        } else {
            $optionTypeRecord = new Stripey_OptionTypeRecord();
            $isNewOptionType  = true;
        }

        $optionTypeRecord->name   = $optionType->name;
        $optionTypeRecord->handle = $optionType->handle;

        $optionTypeRecord->validate();
        $optionType->addErrors($optionTypeRecord->getErrors());

        if (!$optionType->hasErrors()) {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try {
                // Save it!
                $optionTypeRecord->save(false);

                // Now that we have a optionType ID, save it on the model
                if (!$optionType->id) {
                    $optionType->id = $optionTypeRecord->id;
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

    public function deleteOptionTypeById($id)
    {
        $optionType = Stripey_OptionTypeRecord::model()->findById($id);
        $optionType->delete();
    }


}