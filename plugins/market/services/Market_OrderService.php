<?php

namespace Craft;

use Market\Adjusters\Market_AdjusterInterface;
use Market\Adjusters\Market_DiscountAdjuster;
use Market\Adjusters\Market_ShippingAdjuster;
use Market\Adjusters\Market_TaxAdjuster;
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
	 *
	 * @throws Exception
	 */
	private function recalculateOrder(Market_OrderModel $order)
	{
		if (!$order->id) {
			return;
		}

		//calculating adjustments
		$lineItems = craft()->market_lineItem->getAllByOrderId($order->id);

		foreach ($lineItems as $item) { //resetting fields calculated by adjusters
			$item->taxAmount      = 0;
			$item->shippingAmount = 0;
			$item->discountAmount = 0;
		}

		/** @var Market_OrderAdjustmentModel[] $adjustments */
		$adjustments = [];
		foreach ($this->getAdjusters() as $adjuster) {
			$adjustments = array_merge($adjustments, $adjuster->adjust($order, $lineItems));
		}

		//refreshing adjustments
		craft()->market_orderAdjustment->deleteAllByOrderId($order->id);

		foreach ($adjustments as $adjustment) {
			$result = craft()->market_orderAdjustment->save($adjustment);
			if (!$result) {
				$errors = $adjustment->getAllErrors();
				throw new Exception('Error saving order adjustment: ' . implode(', ', $errors));
			}
		}

		//recalculating order amount and saving items
		$order->itemTotal = 0;
		foreach ($lineItems as $item) {
			$result = craft()->market_lineItem->save($item);

			$order->itemTotal += $item->total;

			if (!$result) {
				$errors = $item->getAllErrors();
				throw new Exception('Error saving line item: ' . implode(', ', $errors));
			}
		}

		$order->finalPrice = $order->itemTotal + $order->baseDiscount + $order->baseShippingRate;
		$order->finalPrice = max(0, $order->finalPrice);
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
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save($order)
	{
		if (!$order->id) {
			$orderRecord = new Market_OrderRecord();
		} else {
			$orderRecord = Market_OrderRecord::model()->findById($order->id);

			if (!$orderRecord) {
				throw new Exception(Craft::t('No order exists with the ID “{id}”', ['id' => $order->id]));
			}
		}

		if ($order->completedAt == null){
			$this->recalculateOrder($order);
		}

		$orderRecord->typeId            = $order->typeId;
		$orderRecord->number            = $order->number;
		$orderRecord->itemTotal         = $order->itemTotal;
		$orderRecord->email             = $order->email;
		$orderRecord->completedAt       = $order->completedAt;
		$orderRecord->billingAddressId  = $order->billingAddressId;
		$orderRecord->shippingAddressId = $order->shippingAddressId;
		$orderRecord->shippingMethodId  = $order->shippingMethodId;
		$orderRecord->paymentMethodId   = $order->paymentMethodId;
		$orderRecord->state             = $order->state;
		$orderRecord->couponCode        = $order->couponCode;
		$orderRecord->baseDiscount      = $order->baseDiscount;
		$orderRecord->baseShippingRate  = $order->baseShippingRate;
		$orderRecord->finalPrice        = $order->finalPrice;

		$orderRecord->validate();
		$order->addErrors($orderRecord->getErrors());

		MarketDbHelper::beginStackedTransaction();

		try {
			if (!$order->hasErrors()) {
				if (craft()->elements->saveElement($order)) {
					$orderRecord->id = $order->id;
					$orderRecord->save(false);

					MarketDbHelper::commitStackedTransaction();

					return true;
				}
			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::rollbackStackedTransaction();

		return false;
	}

	/**
	 * Save and set the given addresses to the current cart/order
	 *
	 * @param Market_AddressModel $shippingAddress
	 * @param Market_AddressModel $billingAddress
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function setAddresses(Market_AddressModel $shippingAddress, Market_AddressModel $billingAddress)
	{
		MarketDbHelper::beginStackedTransaction();
		try {
			$result1 = craft()->market_address->save($shippingAddress);
			$result2 = craft()->market_address->save($billingAddress);

			if ($result1 && $result2) {
				$order                    = craft()->market_cart->getCart();
				$order->shippingAddressId = $shippingAddress->id;
				$order->billingAddressId  = $billingAddress->id;

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

	/**
	 * @return Market_AdjusterInterface[]
	 */
	private function getAdjusters()
	{
		return [
			new Market_ShippingAdjuster,
			new Market_DiscountAdjuster,
			new Market_TaxAdjuster,
		];
	}
}