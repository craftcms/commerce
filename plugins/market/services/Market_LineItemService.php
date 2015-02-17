<?php

namespace Craft;

/**
 * Class Market_LineItemService
 * @package Craft
 */
class Market_LineItemService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @return Market_LineItemModel[]
	 */
	public function getAllByOrderId($id)
	{
		$lineItems = Market_LineItemRecord::model()->findAllByAttributes(['orderId' => $id]);
		return Market_LineItemModel::populateModels($lineItems);
	}

	/**
	 * Find line item by order and variant
	 *
	 * @param int $orderId
	 * @param int $variantId
	 * @return Market_LineItemModel
	 */
	public function getByOrderVariant($orderId, $variantId)
	{
		$variant = Market_LineItemRecord::model()->findByAttributes([
			'orderId' => $orderId,
			'variantId' => $variantId,
		]);
		return Market_LineItemModel::populateModel($variant);
	}

	/**
	 * @param Market_LineItemModel $lineItem
	 * @return bool
	 * @throws Exception
	 */
	public function save(Market_LineItemModel $lineItem)
	{
		if (!$lineItem->id) {
			$lineItemRecord = new Market_LineItemRecord();
		} else {
			$lineItemRecord = Market_LineItemRecord::model()->findById($lineItem->id);

			if (!$lineItemRecord) {
				throw new Exception(Craft::t('No line item exists with the ID “{id}”', array('id' => $lineItem->id)));
			}
		}

		$lineItemRecord->variantId = $lineItem->variantId;
		$lineItemRecord->orderId = $lineItem->orderId;
		$lineItemRecord->qty = $lineItem->qty;
		$lineItemRecord->price = $lineItem->price;

		$lineItemRecord->validate();
		$lineItem->addErrors($lineItemRecord->getErrors());

		if (!$lineItem->hasErrors()) {
			$lineItemRecord->save(false);
			$lineItemRecord->id     = $lineItem->id;

			return true;
		}
		return false;
	}

	/**
	 * @param int $variantId
	 * @param int $orderId
	 * @param int $qty
	 * @return Market_LineItemModel
	 */
	public function create($variantId, $orderId, $qty)
	{
		$lineItem = new Market_LineItemModel();
		$lineItem->variantId = $variantId;
		$lineItem->qty = $qty;
		$lineItem->orderId = $orderId;

		$variant = craft()->market_variant->getById($variantId);
		if($variant->id) {
			$lineItem->price = $variant->price;
		}

		return $lineItem;
	}

	/**
	 * @param Market_LineItemModel $lineItem
	 * @return int
	 */
	public function delete($lineItem)
	{
		return Market_LineItemRecord::model()->deleteByPk($lineItem->id);
	}
}