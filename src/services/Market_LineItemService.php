<?php

namespace Craft;

/**
 * Class Market_LineItemService
 *
 * @package Craft
 */
class Market_LineItemService extends BaseApplicationComponent
{

	public function getAllByOrderId($id)
	{
		$order = Market_LineItemRecord::model()->findAllByAttributes(['orderId'=>$id]);

		return Market_LineItemModel::populateModels($order);
	}

	public function delete($lineitem)
	{
		$order = Market_LineItemRecord::model()->findById($lineitem->id);

		return $order->delete();
	}


}