<?php
namespace Craft;

use Commerce\Adjusters\Commerce_AdjusterInterface;
use Commerce\Adjusters\Commerce_DiscountAdjuster;
use Commerce\Adjusters\Commerce_ShippingAdjuster;
use Commerce\Adjusters\Commerce_TaxAdjuster;
use Commerce\Helpers\CommerceDbHelper;

/**
 * Class Commerce_OrderService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrderService extends BaseApplicationComponent
{
	/**
	 * @param Commerce_OrderModel $order
	 *
	 * @throws Exception
	 */
	private function calculateAdjustments (Commerce_OrderModel $order)
	{
		// Don't recalc the totals of completed orders.
		if (!$order->id or $order->dateOrdered != null)
		{
			return;
		}

		//calculating adjustments
		$lineItems = craft()->commerce_lineItem->getAllByOrderId($order->id);

		foreach ($lineItems as $item)
		{ //resetting fields calculated by adjusters
			$item->tax = 0;
			$item->shippingCost = 0;
			$item->discount = 0;
		}

		/** @var Commerce_OrderAdjustmentModel[] $adjustments */
		$adjustments = [];
		foreach ($this->getAdjusters() as $adjuster)
		{
			$adjustments = array_merge($adjustments,
				$adjuster->adjust($order, $lineItems));
		}

		//refreshing adjustments
		craft()->commerce_orderAdjustment->deleteAllByOrderId($order->id);

		foreach ($adjustments as $adjustment)
		{
			$result = craft()->commerce_orderAdjustment->save($adjustment);
			if (!$result)
			{
				$errors = $adjustment->getAllErrors();
				throw new Exception('Error saving order adjustment: '.implode(', ',
						$errors));
			}
		}

		//recalculating order amount and saving items
		$order->itemTotal = 0;
		foreach ($lineItems as $item)
		{
			$result = craft()->commerce_lineItem->save($item);

			$order->itemTotal += $item->total;

			if (!$result)
			{
				$errors = $item->getAllErrors();
				throw new Exception('Error saving line item: '.implode(', ',
						$errors));
			}
		}

		$order->totalPrice = $order->itemTotal + $order->baseDiscount + $order->baseShippingCost;
		$order->totalPrice = max(0, $order->totalPrice);
	}

	/**
	 * Completes an Order
	 *
	 * @param Commerce_OrderModel $order
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function complete (Commerce_OrderModel $order)
	{
		$order->dateOrdered = DateTimeHelper::currentTimeForDb();
		if ($status = craft()->commerce_orderStatus->getDefault())
		{
			$order->orderStatusId = $status->id;
		}

		if (!$this->save($order))
		{
			return false;
		}

		craft()->commerce_cart->forgetCart($order);

		//raising event on order complete
		$event = new Event($this, [
			'order' => $order
		]);
		$this->onOrderComplete($event);

		return true;
	}

	/**
	 * @param int $id
	 *
	 * @return Commerce_OrderModel
	 */
	public function getById ($id)
	{
		return craft()->elements->getElementById($id, 'Commerce_Order');
	}

	/**
	 * @param string $number
	 *
	 * @return Commerce_OrderModel
	 */
	public function getByNumber ($number)
	{
		$criteria = craft()->elements->getCriteria('Commerce_Order');
		$criteria->number = $number;

		return $criteria->first();
	}


	/**
	 * @param int|Commerce_CustomerModel $customer
	 *
	 * @return Commerce_OrderModel[]
	 */
	public function getByCustomer ($customer)
	{
		$id = $customer;
		if ($customer instanceof Commerce_CustomerModel)
		{
			$id = $customer->id;
		}

		$orders = Commerce_OrderRecord::model()->findAllByAttributes(['customerId' => $id]);

		return Commerce_OrderModel::populateModels($orders);
	}

	/**
	 * @param string $email
	 *
	 * @return Commerce_OrderModel[]
	 */
	public function getByEmail ($email)
	{
		$orders = Commerce_OrderRecord::model()->findAllByAttributes(['email' => $email]);

		return Commerce_OrderModel::populateModels($orders);
	}


	/**
	 * @param Commerce_OrderModel $order
	 *
	 * @return bool
	 * @throws \CDbException
	 */
	public function delete ($order)
	{
		return craft()->elements->deleteElementById($order->id);
	}

	/**
	 * @param Commerce_OrderModel $order
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save ($order)
	{
		if (!$order->dateOrdered)
		{
			//raising event
			$event = new Event($this, [
				'order' => $order
			]);
			$this->onBeforeSaveOrder($event);
		}

		if (!$order->id)
		{
			$orderRecord = new Commerce_OrderRecord();
		}
		else
		{
			$orderRecord = Commerce_OrderRecord::model()->findById($order->id);

			if (!$orderRecord)
			{
				throw new Exception(Craft::t('No order exists with the ID “{id}”',
					['id' => $order->id]));
			}
		}

		// Set default shipping method
		if (!$order->shippingMethodId)
		{
			$method = craft()->commerce_shippingMethod->getDefault();
			if ($method)
			{
				$order->shippingMethodId = $method->id;
			}
		}

		// Set default payment method
		if (!$order->paymentMethodId)
		{
			$methods = craft()->commerce_paymentMethod->getAllForFrontend();
			if ($methods)
			{
				$order->paymentMethodId = $methods[0]->id;
			}
		}

		//Only set default addresses on carts
		if (!$order->dateOrdered)
		{

			// Set default shipping address if last used is available
			$lastShippingAddressId = craft()->commerce_customer->getCustomer()->lastUsedShippingAddressId;
			if (!$order->shippingAddressId && $lastShippingAddressId)
			{
				if ($address = craft()->commerce_address->getAddressById($lastShippingAddressId))
				{
					$order->shippingAddressId = $address->id;
					$order->shippingAddressData = JsonHelper::encode($address->attributes);
				}
			}

			// Set default billing address if last used is available
			$lastBillingAddressId = craft()->commerce_customer->getCustomer()->lastUsedBillingAddressId;
			if (!$order->billingAddressId && $lastBillingAddressId)
			{
				if ($address = craft()->commerce_address->getAddressById($lastBillingAddressId))
				{
					$order->billingAddressId = $address->id;
					$order->billingAddressData = JsonHelper::encode($address->attributes);
				}
			}
		}

		if (!$order->customerId)
		{
			$order->customerId = craft()->commerce_customer->getCustomerId();
		}
		else
		{
			// if there is no email set and we have a customer, get their email.
			if (!$order->email)
			{
				$order->email = craft()->commerce_customer->getById($order->customerId)->email;
			}
		}

		// Will not adjust a completed order, we don't want totals to change.
		$this->calculateAdjustments($order);

		$oldStatusId = $orderRecord->orderStatusId;

		$orderRecord->number = $order->number;
		$orderRecord->itemTotal = $order->itemTotal;
		$orderRecord->email = $order->email;
		$orderRecord->dateOrdered = $order->dateOrdered;
		$orderRecord->datePaid = $order->datePaid;
		$orderRecord->billingAddressId = $order->billingAddressId;
		$orderRecord->shippingAddressId = $order->shippingAddressId;
		$orderRecord->shippingMethodId = $order->shippingMethodId;
		$orderRecord->paymentMethodId = $order->paymentMethodId;
		$orderRecord->orderStatusId = $order->orderStatusId;
		$orderRecord->couponCode = $order->couponCode;
		$orderRecord->baseDiscount = $order->baseDiscount;
		$orderRecord->baseShippingCost = $order->baseShippingCost;
		$orderRecord->totalPrice = $order->totalPrice;
		$orderRecord->totalPaid = $order->totalPaid;
		$orderRecord->customerId = $order->customerId;
		$orderRecord->returnUrl = $order->returnUrl;
		$orderRecord->cancelUrl = $order->cancelUrl;
		$orderRecord->message = $order->message;
		$orderRecord->shippingAddressData = $order->shippingAddressData;
		$orderRecord->billingAddressData = $order->billingAddressData;

		$orderRecord->validate();
		$order->addErrors($orderRecord->getErrors());

		CommerceDbHelper::beginStackedTransaction();

		try
		{
			if (!$order->hasErrors())
			{
				if (craft()->elements->saveElement($order))
				{
					//creating order history record
					if ($orderRecord->id && $oldStatusId != $orderRecord->orderStatusId)
					{
						if (!craft()->commerce_orderHistory->createFromOrder($order,
							$oldStatusId)
						)
						{
							throw new Exception('Error saving order history');
						}
					}

					//saving order record
					$orderRecord->id = $order->id;
					$orderRecord->save(false);

					CommerceDbHelper::commitStackedTransaction();

					//raising event
					if (!$order->dateOrdered)
					{
						$event = new Event($this, [
							'order' => $order
						]);
						$this->onSaveOrder($event);
					}

					return true;
				}
			}
		}
		catch (\Exception $e)
		{
			CommerceDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		CommerceDbHelper::rollbackStackedTransaction();

		return false;
	}

	/**
	 * Updates the orders totalPaid and datePaid date and completes order
	 *
	 * @param Commerce_OrderModel $order
	 */
	public function updateOrderPaidTotal (Commerce_OrderModel $order)
	{
		$totalPaid = craft()->commerce_payment->getTotalPaidForOrder($order);

		$order->totalPaid = $totalPaid;

		if ($order->isPaid())
		{
			if ($order->datePaid == null)
			{
				$order->datePaid = DateTimeHelper::currentTimeForDb();
			}
		}

		$this->save($order);

		if (!$order->dateOrdered)
		{
			if ($order->isPaid())
			{
				craft()->commerce_order->complete($order);
			}
			else
			{
				// maybe not paid in full, but authorized enough to complete order.
				$totalAuthorized = craft()->commerce_payment->getTotalAuthorizedForOrder($order);
				if ($totalAuthorized >= $order->totalPrice)
				{
					craft()->commerce_order->complete($order);
				}
			}
		}
	}

	/**
	 * Save and set the given addresses to the current cart/order
	 *
	 * @param Commerce_OrderModel   $order
	 * @param Commerce_AddressModel $shippingAddress
	 * @param Commerce_AddressModel $billingAddress
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function setAddresses (
		Commerce_OrderModel $order,
		Commerce_AddressModel $shippingAddress,
		Commerce_AddressModel $billingAddress
	)
	{
		CommerceDbHelper::beginStackedTransaction();
		try
		{
			$result1 = craft()->commerce_customer->saveAddress($shippingAddress);

			if ($billingAddress->id && $billingAddress->id == $shippingAddress->id)
			{
				$result2 = true;
			}
			else
			{
				$result2 = craft()->commerce_customer->saveAddress($billingAddress);
			}

			$order->setShippingAddress($shippingAddress);
			$order->setBillingAddress($billingAddress);

			if ($result1 && $result2)
			{

				$order->shippingAddressId = $shippingAddress->id;
				$order->billingAddressId = $billingAddress->id;

				$this->save($order);
				CommerceDbHelper::commitStackedTransaction();

				return true;
			}
		}
		catch (\Exception $e)
		{
			CommerceDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		CommerceDbHelper::rollbackStackedTransaction();

		return false;
	}

	/**
	 * Full order recalculation
	 *
	 * @param Commerce_OrderModel $order
	 *
	 * @throws Exception
	 * @throws \Exception
	 */
	public function recalculate (Commerce_OrderModel $order)
	{
		foreach ($order->lineItems as $item)
		{
			if ($item->refreshFromPurchasable())
			{
				if (!craft()->commerce_lineItem->save($item))
				{
					throw new Exception('Error on saving lite item: '.implode(', ',
							$item->getAllErrors()));
				}
			}
			else
			{
				craft()->commerce_lineItem->delete($item);
			}
		}

		$this->save($order);
	}

	/**
	 * @return Commerce_AdjusterInterface[]
	 */
	private function getAdjusters ()
	{
		$adjusters = [
			new Commerce_ShippingAdjuster,
			new Commerce_DiscountAdjuster,
			new Commerce_TaxAdjuster,
		];

		$additional = craft()->plugins->call('registerCommerceOrderAdjusters');

		foreach ($additional as $additionalAdjusters)
		{
			$adjusters = array_merge($adjusters, $additionalAdjusters);
		}

		return $adjusters;
	}

	/**
	 * Event method
	 *
	 * @param \CEvent $event
	 *
	 * @throws \CException
	 */
	public function onOrderComplete (\CEvent $event)
	{
		$this->raiseEvent('onOrderComplete', $event);
	}

	/**
	 * Event: before saving incomplete order
	 * Event params: order(Commerce_OrderModel)
	 *
	 * @param \CEvent $event
	 *
	 * @throws \CException
	 */
	public function onBeforeSaveOrder (\CEvent $event)
	{
		$params = $event->params;
		if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
		{
			throw new Exception('onBeforeSaveOrder event requires "order" param with OrderModel instance');
		}
		$this->raiseEvent('onBeforeSaveOrder', $event);
	}

	/**
	 * Event: before successful saving incomplete order
	 * Event params: order(Commerce_OrderModel)
	 *
	 * @param \CEvent $event
	 *
	 * @throws \CException
	 */
	public function onSaveOrder (\CEvent $event)
	{
		$params = $event->params;
		if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
		{
			throw new Exception('onSaveOrder event requires "order" param with OrderModel instance');
		}
		$this->raiseEvent('onSaveOrder', $event);
	}

}
