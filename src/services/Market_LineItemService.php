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
		$lineItems = Market_LineItemRecord::model()->findAllByAttributes(['orderId' => $id]);

		return Market_LineItemModel::populateModels($lineItems);
	}

	public function delete($lineitem)
	{
		return Market_LineItemRecord::model()->deleteByPk($lineitem->id);
	}
}