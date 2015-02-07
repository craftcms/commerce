<?php

namespace Craft;

/**
 * Class Market_OrderService
 *
 * @package Craft
 */
class Market_OrderService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_OrderModel
	 */
	public function getById($id)
	{
		$order = Market_OrderRecord::model()->findById($id);

		return Market_OrderModel::populateModel($order);
	}

	/**
	 * @param Market_OrderModel $order
	 *
	 * @return bool
	 * @throws \CDbException
	 */
	public function delete($order)
	{
		$order = Market_OrderRecord::model()->findById($order->id);
		return $order->delete();
	}

}