<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Class Commerce_OrderSettingsService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrderSettingsService extends BaseApplicationComponent
{


	/**
	 * @param $id
	 *
	 * @return BaseModel
	 */
	public function getById ($id)
	{
		$orderSettingsRecord = Commerce_OrderSettingsRecord::model()->findByAttributes(['id' => $id]);

		return Commerce_OrderSettingsModel::populateModel($orderSettingsRecord);
	}


	/**
	 * @param string $handle
	 *
	 * @return Commerce_OrderSettingsModel
	 */
	public function getByHandle ($handle)
	{
		$orderSettingsRecord = Commerce_OrderSettingsRecord::model()->findByAttributes(['handle' => $handle]);

		return Commerce_OrderSettingsModel::populateModel($orderSettingsRecord);
	}


	/**
	 * @param Commerce_OrderSettingsModel $orderSettings
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function save (Commerce_OrderSettingsModel $orderSettings)
	{
		if ($orderSettings->id)
		{
			$orderSettingsRecord = Commerce_OrderSettingsRecord::model()->findById($orderSettings->id);
			if (!$orderSettingsRecord)
			{
				throw new Exception(Craft::t('No order settings exists with the ID “{id}”',
					['id' => $orderSettings->id]));
			}

			$oldOrderSettings = Commerce_OrderSettingsModel::populateModel($orderSettingsRecord);
			$isNewOrderSettings = false;
		}
		else
		{
			$orderSettingsRecord = new Commerce_OrderSettingsRecord();
			$isNewOrderSettings = true;
		}

		$orderSettingsRecord->name = $orderSettings->name;
		$orderSettingsRecord->handle = $orderSettings->handle;

		$orderSettingsRecord->validate();
		$orderSettings->addErrors($orderSettingsRecord->getErrors());

		if (!$orderSettings->hasErrors())
		{
			CommerceDbHelper::beginStackedTransaction();
			try
			{
				if (!$isNewOrderSettings && $oldOrderSettings->fieldLayoutId)
				{
					// Drop the old field layout
					craft()->fields->deleteLayoutById($oldOrderSettings->fieldLayoutId);
				}

				// Save the new one
				$fieldLayout = $orderSettings->getFieldLayout();
				craft()->fields->saveLayout($fieldLayout);

				// Update the calendar record/model with the new layout ID
				$orderSettings->fieldLayoutId = $fieldLayout->id;
				$orderSettingsRecord->fieldLayoutId = $fieldLayout->id;

				// Save it!
				$orderSettingsRecord->save(false);

				// Now that we have a calendar ID, save it on the model
				if (!$orderSettings->id)
				{
					$orderSettings->id = $orderSettingsRecord->id;
				}

				CommerceDbHelper::commitStackedTransaction();
			}
			catch (\Exception $e)
			{
				CommerceDbHelper::rollbackStackedTransaction();
				throw $e;
			}

			return true;
		}
		else
		{
			return false;
		}
	}

}