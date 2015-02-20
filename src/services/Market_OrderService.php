<?php

namespace Craft;
use Market\Helpers\MarketDbHelper;

/**
 * Class Market_OrderService
 *
 * @package Craft
 */
class Market_OrderService extends BaseApplicationComponent
{
	/**
	 * @param Market_OrderModel $order
	 */
	private function recalculateOrder(Market_OrderModel $order)
	{
		$lineItems = $order->lineItems ?: craft()->market_lineItem->getAllByOrderId($order->id);
		$order->itemTotal = array_reduce($lineItems, function($sum, $lineItem) {
			return $sum + $lineItem->totalIncTax;
		}, 0);
	}

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
		return Market_OrderRecord::model()->deleteByPk($order->id);
	}

	/**
	 * @param Market_OrderModel $order
	 * @return bool
	 * @throws Exception
	 */
	public function save($order)
	{
		if (!$order->id) {
			$orderRecord = new Market_OrderRecord();
		} else {
			$orderRecord = Market_OrderRecord::model()->findById($order->id);

			if (!$orderRecord) {
				throw new Exception(Craft::t('No order exists with the ID “{id}”', array('id' => $order->id)));
			}
		}

        $this->recalculateOrder($order);

		$orderRecord->typeId 			= $order->typeId;
		$orderRecord->number 			= $order->number;
		$orderRecord->adjustmentTotal 	= $order->adjustmentTotal;
		$orderRecord->itemTotal 		= $order->itemTotal;
		$orderRecord->email 			= $order->email;
		$orderRecord->completedAt 		= $order->completedAt;
		$orderRecord->billingAddressId 	= $order->billingAddressId;
		$orderRecord->shippingAddressId = $order->shippingAddressId;
		$orderRecord->state 			= $order->state;

		$orderRecord->validate();
		$order->addErrors($orderRecord->getErrors());

		if (!$order->hasErrors()) {
			if (craft()->elements->saveElement($order)) {
				$orderRecord->id = $order->id;
				$orderRecord->save(false);

				return true;
			}
		}
		return false;
	}

	/**
	 * Save and set the given addresses to the current cart/order
	 *
	 * @param Market_AddressModel $shippingAddress
	 * @param Market_AddressModel $billingAddress
	 * @return bool
	 * @throws \Exception
	 */
	public function setAddresses(Market_AddressModel $shippingAddress, Market_AddressModel $billingAddress)
	{
		MarketDbHelper::beginStackedTransaction();
		try {
			$result1 = craft()->market_address->save($shippingAddress);
			$result2 = craft()->market_address->save($billingAddress);

			if($result1 && $result2) {
				$order = craft()->market_cart->getCart();
				$order->shippingAddressId = $shippingAddress->id;
				$order->billingAddressId = $billingAddress->id;

				craft()->market_customer->saveAddress($shippingAddress);
				craft()->market_customer->saveAddress($billingAddress);

				$this->save($order);
				MarketDbHelper::commitStackedTransaction();
				return true;
			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::rollbackStackedTransaction();
		return false;
	}
}