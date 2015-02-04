<?php

namespace Craft;

class Stripey_OptionValueService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Stripey_OptionValueModel[]
	 */
	public function getAllByOptionTypeId($id)
	{
		$optionValueRecords = Stripey_OptionValueRecord::model()->findAllByAttributes(array('optionTypeId' => $id));

		return Stripey_OptionValueModel::populateModels($optionValueRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Stripey_OptionValueModel
	 */
	public function getById($id)
	{
		$optionValueRecord = Stripey_OptionValueRecord::model()->findById($id);

		return Stripey_OptionValueModel::populateModel($optionValueRecord);
	}

	/**
	 * @param Stripey_OptionTypeModel    $optionType
	 * @param Stripey_OptionValueModel[] $optionValues
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function saveOptionValuesForOptionType($optionType, $optionValues)
	{
		// Check for a real optionType
		if (!craft()->stripey_optionType->getById($optionType->id)) {
			throw new Exception(Craft::t('No Option Type exists with the ID “{id}”', array('id' => $id)));
		}

		// Delete all optionValues that were removed
		$this->_deleteOptionValuesRemoved($optionType, $optionValues);

		$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
		try {
			foreach ($optionValues as $optionValue) {
				$optionValueRecord = Stripey_OptionValueRecord::model()->findByAttributes(array(
					'id'           => $optionValue->id,
					'optionTypeId' => $optionType->id
				));

				if (!$optionValueRecord) {
					$optionValueRecord = new Stripey_OptionValueRecord();
				}

				$optionValueRecord->name         = $optionValue->name;
				$optionValueRecord->displayName  = $optionValue->displayName;
				$optionValueRecord->position     = $optionValue->position;
				$optionValueRecord->optionTypeId = $optionType->id;
				$optionValueRecord->save(false);
			}

			if ($transaction !== NULL) {
				$transaction->commit();
			}
		} catch (\Exception $e) {
			if ($transaction !== NULL) {
				$transaction->rollback();
			}

			throw $e;
		}

		return true;
	}

	/**
	 * @param Stripey_OptionTypeModel    $optionType
	 * @param Stripey_OptionValueModel[] $optionValues
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

	/**
	 * @param int $id
	 *
	 * @throws \CDbException
	 */
	public function deleteById($id)
	{
		$optionType = Stripey_OptionTypeRecord::model()->findById($id);
		$optionType->delete();
	}

}