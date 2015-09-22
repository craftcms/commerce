<?php
namespace Craft;

/**
 * Order adjustment service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrderAdjustmentService extends BaseApplicationComponent
{
	/**
	 * @param int $orderId
	 *
	 * @return Commerce_OrderAdjustmentModel[]
	 */
	public function getAllByOrderId ($orderId)
	{
		$records = Commerce_OrderAdjustmentRecord::model()->findAllByAttributes(['orderId' => $orderId]);

		return Commerce_OrderAdjustmentModel::populateModels($records);
	}

	/**
	 * @param Commerce_OrderAdjustmentModel $model
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save (Commerce_OrderAdjustmentModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_OrderAdjustmentRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No order Adjustment exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_OrderAdjustmentRecord();
		}

		$fields = [
			'name',
			'type',
			'description',
			'amount',
			'orderId',
			'optionsJson'
		];
		foreach ($fields as $field)
		{
			$record->$field = $model->$field;
		}
		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			$record->save(false);
			$model->id = $record->id;

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param int $orderId
	 *
	 * @return int
	 */
	public function deleteAllByOrderId ($orderId)
	{
		return Commerce_OrderAdjustmentRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
	}
}