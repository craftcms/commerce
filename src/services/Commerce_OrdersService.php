<?php
namespace Craft;

use Commerce\Adjusters\Commerce_AdjusterInterface;
use Commerce\Adjusters\Commerce_DiscountAdjuster;
use Commerce\Adjusters\Commerce_ShippingAdjuster;
use Commerce\Adjusters\Commerce_TaxAdjuster;
use Commerce\Helpers\CommerceCurrencyHelper;

/**
 * Class Commerce_OrdersService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrdersService extends BaseApplicationComponent
{

    /**
     * @var
     */
    private $_lineItemsById;

    /**
     * @var
     */
    private $_adjustmentsById;

    /**
     * @param int $id
     *
     * @return Commerce_OrderModel|null
     */
    public function getOrderById($id)
    {
        return craft()->elements->getElementById($id, 'Commerce_Order');
    }

    /**
     * @param string $number
     *
     * @return Commerce_OrderModel|null
     */
    public function getOrderByNumber($number)
    {
        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->number = $number;

        return $criteria->first();
    }

    /**
     * @param int|Commerce_CustomerModel $customer
     *
     * @return Commerce_OrderModel[]|null
     */
    public function getOrdersByCustomer($customer)
    {
        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->customer = $customer;
        $criteria->isCompleted = true;
        $criteria->limit = null;

        return $criteria->find();
    }

    /**
     * @param string $email
     *
     * @return Commerce_OrderModel[]
     */
    public function getOrdersByEmail($email)
    {
        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->email = $email;
        $criteria->isCompleted = true;
        $criteria->limit = null;

        return $criteria->find();
    }

    /**
     * @param Commerce_OrderModel $order
     *
     * @return bool
     * @throws \CDbException
     */
    public function deleteOrder($order)
    {
        return craft()->elements->deleteElementById($order->id);
    }

    /**
     * Updates the orders totalPaid and datePaid date and completes order
     *
     * @param Commerce_OrderModel $order
     */
    public function updateOrderPaidTotal(Commerce_OrderModel $order)
    {
        $totalPaid = craft()->commerce_payments->getTotalPaidForOrder($order);

        $order->totalPaid = $totalPaid;

        if ($order->isPaid())
        {
            if ($order->datePaid == null)
            {
                $order->datePaid = DateTimeHelper::currentTimeForDb();
            }
        }

        $originalShouldRecalculate = $order->getShouldRecalculateAdjustments();
        $order->setShouldRecalculateAdjustments(false);
        $this->saveOrder($order);
        $order->setShouldRecalculateAdjustments($originalShouldRecalculate);

        if (!$order->isCompleted)
        {
            if ($order->isPaid())
            {
                $this->completeOrder($order);
            }
            else
            {
                // maybe not paid in full, but authorized enough to complete order.
                $totalAuthorized = craft()->commerce_payments->getTotalAuthorizedForOrder($order);
                if ($totalAuthorized >= $order->totalPrice)
                {
                    $this->completeOrder($order);
                }
            }
        }
    }

    /**
     * @param Commerce_OrderModel $order
     *
     * @return bool
     * @throws \Exception
     */
    public function saveOrder($order)
    {
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

        // Set default payment method
        if (!$order->paymentMethodId)
        {
            $methods = craft()->commerce_paymentMethods->getAllFrontEndPaymentMethods();
            if (count($methods))
            {
                $order->paymentMethodId = $methods[0]->id;
            }
        }

        // Get the customer ID from the session
        if (!$order->customerId && !craft()->isConsole())
        {
            $order->customerId = craft()->commerce_customers->getCustomerId();
        }

        $order->email = craft()->commerce_customers->getCustomerById($order->customerId)->email;

        // Will not adjust a completed order, we don't want totals to change.
        $this->calculateAdjustments($order);

        $oldStatusId = $orderRecord->orderStatusId;

        //raising event
        $event = new Event($this, [
            'order' => $order
        ]);
        $this->onBeforeSaveOrder($event);

        $orderRecord->number = $order->number;
        $orderRecord->itemTotal = $order->itemTotal;
        $orderRecord->email = $order->email;
        $orderRecord->isCompleted = $order->isCompleted;
        $orderRecord->dateOrdered = $order->dateOrdered;
        $orderRecord->datePaid = $order->datePaid;
        $orderRecord->billingAddressId = $order->billingAddressId;
        $orderRecord->shippingAddressId = $order->shippingAddressId;
        $orderRecord->shippingMethod = $order->getShippingMethodHandle();
        $orderRecord->paymentMethodId = $order->paymentMethodId;
        $orderRecord->orderStatusId = $order->orderStatusId;
        $orderRecord->couponCode = $order->couponCode;
        $orderRecord->baseDiscount = $order->baseDiscount;
        $orderRecord->baseShippingCost = $order->baseShippingCost;
        $orderRecord->baseTax = $order->baseTax;
        $orderRecord->baseTaxIncluded = $order->baseTaxIncluded;
        $orderRecord->totalPrice = $order->totalPrice;
        $orderRecord->totalPaid = $order->totalPaid;
        $orderRecord->currency = $order->currency;
        $orderRecord->lastIp = $order->lastIp;
        $orderRecord->orderLocale = $order->orderLocale;
        $orderRecord->paymentCurrency = $order->paymentCurrency;
        $orderRecord->customerId = $order->customerId;
        $orderRecord->returnUrl = $order->returnUrl;
        $orderRecord->cancelUrl = $order->cancelUrl;
        $orderRecord->message = $order->message;

        $orderRecord->validate();
        $order->addErrors($orderRecord->getErrors());

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        try
        {
            if (!$order->hasErrors() && $event->performAction)
            {
                if (craft()->elements->saveElement($order))
                {

                    $orderRecord->id = $order->id;
                    $orderRecord->save(false);

                    if ($transaction !== null)
                    {
                        $transaction->commit();
                    }

                    //raising event
                    $event = new Event($this, [
                        'order' => $order
                    ]);
                    $this->onSaveOrder($event);

                    //creating order history record
                    if ($orderRecord->id && $oldStatusId != $orderRecord->orderStatusId)
                    {
                        if (!craft()->commerce_orderHistories->createOrderHistoryFromOrder($order,
                            $oldStatusId)
                        )
                        {
                            CommercePlugin::log('Error saving order history after Order save.', LogLevel::Error);
                            throw new Exception('Error saving order history');
                        }
                    }

                    return true;
                }
            }
        }
        catch (\Exception $e)
        {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        if ($transaction !== null)
        {
            $transaction->rollback();
        }

        return false;
    }

    /**
     * Event: before saving order
     * Event params: order(Commerce_OrderModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSaveOrder(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onBeforeSaveOrder event requires "order" param with OrderModel instance');
        }
        $this->raiseEvent('onBeforeSaveOrder', $event);
    }

    /**
     * @param Commerce_OrderModel $order
     *
     * @throws Exception
     */
    private function calculateAdjustments(Commerce_OrderModel $order)
    {
        if (!$order->id || !$order->getShouldRecalculateAdjustments())
        {
            return;
        }

        //calculating adjustments
        $lineItems = craft()->commerce_lineItems->getAllLineItemsByOrderId($order->id);

        // reset base totals
        $order->baseDiscount = 0;
        $order->baseShippingCost = 0;
        $order->baseTax = 0;
        $order->baseTaxIncluded = 0;
        $order->itemTotal = 0;
        foreach ($lineItems as $key => $item)
        {
            if (!$item->refreshFromPurchasable())
            {
                $this->removeLineItemFromOrder($order, $item);
                // We have changed the cart contents so recalculate the order.
                $this->calculateAdjustments($order);
                return;
            }

            $item->tax = 0;
            $item->taxIncluded = 0;
            $item->shippingCost = 0;
            $item->discount = 0;
            // Need to have an initial itemTotal for use by adjusters.
            $order->itemTotal += $item->getTotal();
        }

        $order->setLineItems($lineItems);

        // reset adjustments
        $order->setAdjustments([]);
        craft()->commerce_orderAdjustments->deleteAllOrderAdjustmentsByOrderId($order->id);

        // collect new adjustments
        foreach ($this->getAdjusters($order) as $adjuster)
        {
            $adjustments = $adjuster->adjust($order, $lineItems);
            $order->setAdjustments(array_merge($order->getAdjustments(), $adjustments));
        }

        // save new adjustment models
        foreach ($order->getAdjustments() as $adjustment)
        {
            $result = craft()->commerce_orderAdjustments->saveOrderAdjustment($adjustment);
            if (!$result)
            {
                $errors = $adjustment->getAllErrors();
                throw new Exception('Error saving order adjustment: '.implode(', ', $errors));
            }
        }

        //recalculating order amount and saving items
        $order->itemTotal = 0;
        foreach ($lineItems as $item)
        {
            $result = craft()->commerce_lineItems->saveLineItem($item);
            $order->itemTotal += $item->total;
        }

        $itemSubtotal = $order->getItemSubtotal();
        $adjustmentSubtotal = $order->getAdjustmentSubtotal();
        $totalPrice = ($itemSubtotal + $adjustmentSubtotal);

        $baseDiscount = $order->baseDiscount;
        $baseShipping = $order->baseShippingCost;
        $baseTax = $order->baseTax;
        $itemTotal = $order->itemTotal;
        $order->totalPrice = ($baseDiscount + $baseShipping + $baseTax + $itemTotal);

        $same = (bool)$totalPrice == $order->totalPrice;

        if (!$same)
        {
            CommercePlugin::log(Craft::t('Total of line items after adjustments does not equal total of adjustment amounts plus original sale prices for order #{orderNumber}', ['orderNumber' => $order->number]), LogLevel::Warning, true);
        }

        $order->totalPrice = CommerceCurrencyHelper::round(max(0, $order->totalPrice));
        
        // Since shipping adjusters run on the original price, pre discount, let's recalculate
        // if the currently selected shipping method is now not available.
        $availableMethods = craft()->commerce_shippingMethods->getAvailableShippingMethods($order);
        if ($order->getShippingMethodHandle())
        {
            if (!isset($availableMethods[$order->getShippingMethodHandle()]) || empty($availableMethods))
            {
                $order->shippingMethod = null;
                $this->calculateAdjustments($order);
                return;
            }
        }
        
    }

    /**
     * @param Commerce_OrderModel $order
     * @return Commerce_AdjusterInterface[]
     */
    private function getAdjusters($order = null)
    {
        $adjusters = [
            200 => new Commerce_ShippingAdjuster,
            400 => new Commerce_DiscountAdjuster,
            600 => new Commerce_TaxAdjuster,
        ];

        // Additional adjuster can be returned by the plugins.
        $additional = craft()->plugins->call('commerce_registerOrderAdjusters', [&$adjusters, $order]);

        $orderIndex = 800;
        foreach ($additional as $additionalAdjusters)
        {
            foreach ($additionalAdjusters as $key => $additionalAdjuster)
            {
                $orderIndex += 1;

                // Not expecting more than 100 adjusters per plugin.
                if ($key < 100 || $key > 800)
                {
                    $additionalAdjusters[$orderIndex] = $additionalAdjusters[$key];
                    unset($additionalAdjusters[$key]);
                }
            }

            $adjusters = $adjusters + $additionalAdjusters;
        }

        ksort($adjusters);

        // Allow plugins to modify the adjusters
        craft()->plugins->call('commerce_modifyOrderAdjusters', [&$adjusters, $order]);

        return $adjusters;
    }

    /**
     * Event: before successful saving incomplete order
     * Event params: order(Commerce_OrderModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveOrder(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onSaveOrder event requires "order" param with OrderModel instance');
        }
        $this->raiseEvent('onSaveOrder', $event);
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
    public function completeOrder(Commerce_OrderModel $order)
    {

        if ($order->isCompleted)
        {
            return true;
        }

        $order->isCompleted = true;
        $order->dateOrdered = DateTimeHelper::currentTimeForDb();
        $order->orderStatusId = craft()->commerce_orderStatuses->getDefaultOrderStatusId();

        //raising event on order complete
        $event = new Event($this, ['order' => $order]);
        $this->onBeforeOrderComplete($event);

        if ($this->saveOrder($order))
        {
            // Run order complete handlers directly.
            craft()->commerce_discounts->orderCompleteHandler($order);
            craft()->commerce_variants->orderCompleteHandler($order);
            craft()->commerce_customers->orderCompleteHandler($order);

            //raising event on order complete
            $event = new Event($this, ['order' => $order]);
            $this->onOrderComplete($event);

            return true;
        }

        CommercePlugin::log(Craft::t('Could not mark order {number} as complete. Order save failed during order completion with errors: {errors}',
            ['number' => $order->number, 'order' => json_encode($order->getAllErrors())]), LogLevel::Error, true);
        return false;
    }

    /**
     * Event method
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeOrderComplete(\CEvent $event)
    {
        $this->raiseEvent('onBeforeOrderComplete', $event);
    }

    /**
     * Event method
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onOrderComplete(\CEvent $event)
    {
        $this->raiseEvent('onOrderComplete', $event);
    }
    
    /**
     * Save and set the given addresses to the current cart/order
     *
     * @param Commerce_OrderModel   $order
     * @param Commerce_AddressModel $shippingAddress
     * @param Commerce_AddressModel $billingAddress
     * @param string $error
     *
     * @return bool
     * @throws \Exception
     */
    public function setOrderAddresses(
        Commerce_OrderModel $order,
        Commerce_AddressModel $shippingAddress,
        Commerce_AddressModel $billingAddress,
        &$error = ''
    )
    {
        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        try
        {
            if (!$order->id && !$this->saveOrder($order))
            {
                throw new Exception(Craft::t('Error on creating empty cart'));
            }

            $customerId = $order->customerId;
            $currentCustomerAddressIds = craft()->commerce_customers->getAddressIds($customerId);

            $ownAddress = true;
            // Customers can only set addresses that are theirs
            if ($shippingAddress->id && !in_array($shippingAddress->id, $currentCustomerAddressIds))
            {
                $ownAddress = false;
            }
            // Customer can only set addresses that are theirs
            if ($billingAddress->id && !in_array($billingAddress->id, $currentCustomerAddressIds))
            {
                $ownAddress = false;
            }

            if (!$ownAddress)
            {
                $error = Craft::t('Can not choose an address ID that does not belong to the customer.');
            }

            $result1 = craft()->commerce_customers->saveAddress($shippingAddress);

            if (($billingAddress->id && $billingAddress->id == $shippingAddress->id) || $shippingAddress === $billingAddress)
            {
                $result2 = true;
            }
            else
            {
                $result2 = craft()->commerce_customers->saveAddress($billingAddress);
            }

            $order->setShippingAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);

            if ($result1 && $result2)
            {

                $order->shippingAddressId = $shippingAddress->id;
                $order->billingAddressId = $billingAddress->id;

                $this->saveOrder($order);
                if ($transaction !== null)
                {
                    $transaction->commit();
                }

                return true;
            }
        } catch (\Exception $e)
        {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        if ($transaction !== null)
        {
            $transaction->rollback();
        }

        return false;
    }

    /**
     * Full order recalculation
     *
     * @param Commerce_OrderModel $order
     *
     * @deprecated Use the saveOrder method instead.
     *
     * @throws \Exception
     */
    public function recalculateOrder(Commerce_OrderModel $order)
    {
        craft()->deprecator->log('Commerce_OrderService::recalculateOrder():removed', 'You should no longer use the `Commerce_OrderService::recalculateOrder()` method. You can simply save the order with `Commerce_OrderService::saveOrder()` while `order.isCompleted` is false, which will recalculate the order.');

        $this->saveOrder($order);
    }

    /**
     * @param Commerce_OrderModel    $order
     * @param Commerce_LineItemModel $lineItem
     *
     * @return bool
     */
    public function removeLineItemFromOrder(Commerce_OrderModel $order, Commerce_LineItemModel $lineItem)
    {
        $success = false;
        $lineItems = $order->getLineItems();
        foreach ($lineItems as $key => $item)
        {
            if ($lineItem->id == $item->id)
            {
                if ($success = Commerce_LineItemRecord::model()->deleteByPk($lineItem->id));
                {
                    unset($lineItems[$key]);
                    $order->setLineItems($lineItems);
                }
            }
        }

        return $success;
    }

}
