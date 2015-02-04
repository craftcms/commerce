<?php

namespace Craft;

class Market_OptionValueService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_OptionValueModel[]
	 */
	public function getAllByOptionTypeId($id)
	{
		$optionValueRecords = Market_OptionValueRecord::model()->findAllByAttributes(array('optionTypeId' => $id));

		return Market_OptionValueModel::populateModels($optionValueRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_OptionValueModel
	 */
	public function getById($id)
	{
		$optionValueRecord = Market_OptionValueRecord::model()->findById($id);

		return Market_OptionValueModel::populateModel($optionValueRecord);
	}

	/**
	 * @param Market_OptionTypeModel    $optionType
	 * @param Market_OptionValueModel[] $optionValues
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function saveOptionValuesForOptionType($optionType, $optionValues)
	{
		// Check for a real optionType
		if (!craft()->market_optionType->getById($optionType->id)) {
			throw new Exception(Craft::t('No Option Type exists with the ID “{id}”', array('id' => $id)));
		}

		// Delete all optionValues that were removed
		$this->_deleteOptionValuesRemoved($optionType, $optionValues);

		$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
		try {
			foreach ($optionValues as $optionValue) {
				$optionValueRecord = Market_OptionValueRecord::model()->findByAttributes(array(
					'id'           => $optionValue->id,
					'optionTypeId' => $optionType->id
				));

				if (!$optionValueRecord) {
					$optionValueRecord = new Market_OptionValueRecord();
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
	 * @param Market_OptionTypeModel    $optionType
	 * @param Market_OptionValueModel[] $optionValues
	 */
	private function _deleteOptionValuesRemoved($optionType, $optionValues)
	{
		$newIds   = array_filter(array_map(function ($optionValue) {
			return $optionValue['id'];
		}, $optionValues));
		$criteria = new \CDbCriteria();
		$criteria->addColumnCondition(array('optionTypeId' => $optionType->id));
		$criteria->addNotInCondition("id", $newIds);
		Market_OptionValueRecord::model()->deleteAll($criteria);
	}

	/**
	 * @param int $id
	 *
	 * @throws \CDbException
	 */
	public function deleteById($id)
	{
		$optionType = Market_OptionTypeRecord::model()->findById($id);
		$optionType->delete();
	}

}