<?php

namespace Craft;

/**
 * Class Stripey_OrderService
 *
 * @package Craft
 */
class Stripey_OrderService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Stripey_OrderModel
	 */
	public function getById($id)
	{
		$order = Stripey_OrderRecord::model()->findById($id);

		return Stripey_OrderModel::populateModel($order);
	}

	/**
	 * @param Stripey_OrderModel $order
	 *
	 * @return bool
	 * @throws \CDbException
	 */
	public function delete($order)
	{
		$order = Stripey_OrderRecord::model()->findById($order->id);
		$order->delete();
	}

}