<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;
use Commerce\Interfaces\Purchasable;

/**
 * Line item service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_LineItemsService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_LineItemModel[]
	 */
	public function getAllByOrderId ($id)
	{
		$lineItems = Commerce_LineItemRecord::model()->findAllByAttributes(['orderId' => $id]);

		return Commerce_LineItemModel::populateModels($lineItems);
	}

	/**
	 * Find line item by order and variant
	 *
	 * @param int $orderId
	 * @param int $purchasableId
	 *
	 * @return Commerce_LineItemModel
	 */
	public function getByOrderPurchasable ($orderId, $purchasableId)
	{
		$purchasable = Commerce_LineItemRecord::model()->findByAttributes([
			'orderId'       => $orderId,
			'purchasableId' => $purchasableId,
		]);

		return Commerce_LineItemModel::populateModel($purchasable);
	}


	/**
	 * Update line item and recalculate order
	 *
	 * @TODO check that the line item belongs to the current user
	 *
	 * @param Commerce_OrderModel    $order
	 * @param Commerce_LineItemModel $lineItem
	 * @param string               $error
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function update (Commerce_OrderModel $order, Commerce_LineItemModel $lineItem, &$error = '')
	{
		if ($this->save($lineItem))
		{
			craft()->commerce_orders->save($order);

			return true;
		}
		else
		{
			$errors = $lineItem->getAllErrors();
			$error = array_pop($errors);

			return false;
		}
	}

	/**
	 * @param Commerce_LineItemModel $lineItem
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save (Commerce_LineItemModel $lineItem)
	{

		if ($lineItem->qty <= 0 && $lineItem->id)
		{
			$this->delete($lineItem);

			return true;
		}

		if (!$lineItem->id)
		{
			$lineItemRecord = new Commerce_LineItemRecord();
		}
		else
		{
			$lineItemRecord = Commerce_LineItemRecord::model()->findById($lineItem->id);

			if (!$lineItemRecord)
			{
				throw new Exception(Craft::t('No line item exists with the ID “{id}”',
					['id' => $lineItem->id]));
			}
		}

		$lineItem->total = (($lineItem->price + $lineItem->saleAmount)
				* $lineItem->qty)
			+ $lineItem->tax + $lineItem->discount + $lineItem->shippingCost;

		$lineItemRecord->purchasableId = $lineItem->purchasableId;
		$lineItemRecord->orderId = $lineItem->orderId;
		$lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;

		$lineItemRecord->qty = $lineItem->qty;
		$lineItemRecord->price = $lineItem->price;

		$lineItemRecord->weight = $lineItem->weight;
		$lineItemRecord->snapshot = $lineItem->snapshot;
		$lineItemRecord->note = $lineItem->note;

		$lineItemRecord->saleAmount = $lineItem->saleAmount;
		$lineItemRecord->salePrice = $lineItem->salePrice;
		$lineItemRecord->tax = $lineItem->tax;
		$lineItemRecord->discount = $lineItem->discount;
		$lineItemRecord->shippingCost = $lineItem->shippingCost;
		$lineItemRecord->total = $lineItem->total;

		// Cant have discounts making things less than zero.
		if ($lineItemRecord->total < 0)
		{
			$lineItemRecord->total = 0;
		}

		$lineItemRecord->validate();

		/** @var \Commerce\Interfaces\Purchasable $purchasable */
		$purchasable = craft()->elements->getElementById($lineItem->purchasableId);
		$purchasable->validateLineItem($lineItem);

		$lineItem->addErrors($lineItemRecord->getErrors());

		CommerceDbHelper::beginStackedTransaction();
		try
		{
			if (!$lineItem->hasErrors())
			{
				$lineItemRecord->save(false);
				$lineItemRecord->id = $lineItem->id;

				CommerceDbHelper::commitStackedTransaction();

				return true;
			}
		}
		catch (\Exception $e)
		{
			CommerceDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		return false;
	}

	/**
	 * @param int $id
	 *
	 * @return Commerce_LineItemModel
	 */
	public function getById ($id)
	{
		$lineItem = Commerce_LineItemRecord::model()->findById($id);

		return Commerce_LineItemModel::populateModel($lineItem);
	}

	/**
	 * @param int $purchasableId
	 * @param int $orderId
	 * @param int $qty
	 *
	 * @return Commerce_LineItemModel
	 */
	public function create ($purchasableId, $orderId, $qty)
	{
		$lineItem = new Commerce_LineItemModel();
		$lineItem->purchasableId = $purchasableId;
		$lineItem->qty = $qty;
		$lineItem->orderId = $orderId;

		/** @var \Commerce\Interfaces\Purchasable $purchasable */
		$purchasable = craft()->elements->getElementById($purchasableId);

		if ($purchasable && $purchasable instanceof Purchasable)
		{
			$lineItem->fillFromPurchasable($purchasable);
		}
		else
		{
			$lineItem->addError('purchasableId', Craft::t('Item not found or is not a purchasable.'));
		}

		return $lineItem;
	}

	/**
	 * @param Commerce_LineItemModel $lineItem
	 *
	 * @return int
	 */
	public function delete ($lineItem)
	{
		return Commerce_LineItemRecord::model()->deleteByPk($lineItem->id);
	}

	/**
	 * @param int $orderId
	 *
	 * @return int
	 */
	public function deleteAllByOrderId ($orderId)
	{
		return Commerce_LineItemRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
	}
}