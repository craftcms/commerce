<?php

namespace Craft;


class Stripey_OptionValueService extends BaseApplicationComponent
{
    public function getAllOptionValuesByOptionTypeId($id)
    {
        $find               = array('optionTypeId' => $id);
        $optionValueRecords = Stripey_OptionValueRecord::model()->findAllByAttributes($find);

        return Stripey_OptionValueModel::populateModels($optionValueRecords);
    }

    public function getOptionValueById($id)
    {
        $optionValueRecord = Stripey_OptionValueRecord::model()->findById($id);

        return Stripey_OptionValueModel::populateModel($optionValueRecord);
    }

    public function saveOptionValuesForOptionType($optionType, $optionValues)
    {

        // Check for a real optionType
        if (!craft()->stripey_optionType->getOptionTypeById($optionType->id)) {
            throw new Exception(Craft::t('No Option Type exists with the ID “{id}”', array('id' => $id)));
        }

        // Delete all optionValues that were removed
        $this->_deleteOptionValuesRemoved($optionType, $optionValues);

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        try {

            foreach ($optionValues as $optionValue) {
                $params = array('id' => $optionValue->id, 'optionTypeId' => $optionType->id);
                $optionValueRecord   = Stripey_OptionValueRecord::model()->findByAttributes($params);

                if (!$optionValueRecord) {
                    $optionValueRecord = new Stripey_OptionValueRecord();
                }

                $optionValueRecord->name = $optionValue->name;
                $optionValueRecord->displayName = $optionValue->displayName;
                $optionValueRecord->position = $optionValue->position;
                $optionValueRecord->optionTypeId = $optionType->id;
                $optionValueRecord->save(false);
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
    }

    /**
     * @param $optionType
     * @param $optionValues
     */
    private function _deleteOptionValuesRemoved($optionType, $optionValues)
    {
        $newIds   = array_filter(array_map(function ($optionValue) {
            return $optionValue['id'];
        }, $optionValues));
        $criteria = new \CDbCriteria();
        $criteria->addColumnCondition(array('optionTypeId' => $optionType->id));
        $criteria->addNotInCondition("id", $newIds);
        Stripey_OptionValueRecord::model()->deleteAll($criteria);
    }

    public function deleteOptionTypeById($id)
    {
        $optionType = Stripey_OptionTypeRecord::model()->findById($id);
        $optionType->delete();
    }


}