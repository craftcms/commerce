<?php
namespace Craft;

use Commerce\Adjusters\Commerce_AdjusterInterface;
use Commerce\Adjusters\Commerce_DiscountAdjuster;
use Commerce\Adjusters\Commerce_ShippingAdjuster;
use Commerce\Adjusters\Commerce_TaxAdjuster;
use Commerce\Helpers\CommerceDbHelper;

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
        $criteria->dateOrdered = "NOT NULL";
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
        $criteria->dateOrdered = "NOT NULL";
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

        if ($order->isPaid()) {
            if ($order->datePaid == null) {
                $order->datePaid = DateTimeHelper::currentTimeForDb();
            }
        }

        $this->saveOrder($order);

        if (!$order->dateOrdered) {
            if ($order->isPaid()) {
                craft()->commerce_orders->completeOrder($order);
            } else {
                // maybe not paid in full, but authorized enough to complete order.
                $totalAuthorized = craft()->commerce_payments->getTotalAuthorizedForOrder($order);
                if ($totalAuthorized >= $order->totalPrice) {
                    craft()->commerce_orders->completeOrder($order);
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
        if (!$order->id) {
            $orderRecord = new Commerce_OrderRecord();
        } else {
            $orderRecord = Commerce_OrderRecord::model()->findById($order->id);

            if (!$orderRecord) {
                throw new Exception(Craft::t('No order exists with the ID “{id}”',
                    ['id' => $order->id]));
            }
        }

        // Set default payment method
        if (!$order->paymentMethodId) {
            $methods = craft()->commerce_paymentMethods->getAllFrontEndPaymentMethods();
            if (count($methods)) {
                $order->paymentMethodId = $methods[0]->id;
            }
        }

        //Only set default addresses on carts
        if (!$order->dateOrdered) {

            // Set default shipping address if last used is available
            $lastShippingAddressId = craft()->commerce_customers->getCustomer()->lastUsedShippingAddressId;
            if (!$order->shippingAddressId && $lastShippingAddressId) {
                if ($address = craft()->commerce_addresses->getAddressById($lastShippingAddressId)) {
                    $order->shippingAddressId = $address->id;
                }
            }

            // Set default billing address if last used is available
            $lastBillingAddressId = craft()->commerce_customers->getCustomer()->lastUsedBillingAddressId;
            if (!$order->billingAddressId && $lastBillingAddressId) {
                if ($address = craft()->commerce_addresses->getAddressById($lastBillingAddressId)) {
                    $order->billingAddressId = $address->id;
                }
            }
        }

        if (!$order->customerId) {
            $order->customerId = craft()->commerce_customers->getCustomerId();
        }

        $order->email = craft()->commerce_customers->getCustomerById($order->customerId)->email;

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
        $orderRecord->shippingMethod = $order->getShippingMethodHandle();
        $orderRecord->paymentMethodId = $order->paymentMethodId;
        $orderRecord->orderStatusId = $order->orderStatusId;
        $orderRecord->couponCode = $order->couponCode;
        $orderRecord->baseDiscount = $order->baseDiscount;
        $orderRecord->baseShippingCost = $order->baseShippingCost;
        $orderRecord->totalPrice = $order->totalPrice;
        $orderRecord->totalPaid = $order->totalPaid;
        $orderRecord->currency = $order->currency;
        $orderRecord->customerId = $order->customerId;
        $orderRecord->returnUrl = $order->returnUrl;
        $orderRecord->cancelUrl = $order->cancelUrl;
        $orderRecord->message = $order->message;

        $orderRecord->validate();
        $order->addErrors($orderRecord->getErrors());

        CommerceDbHelper::beginStackedTransaction();

        try {
            if (!$order->hasErrors()) {
                if (craft()->elements->saveElement($order)) {

                    $orderRecord->id = $order->id;

                    //raising event
                    $event = new Event($this, [
                        'order' => $order
                    ]);
                    $this->onBeforeSaveOrder($event);

                    if($event->performAction){
                        $orderRecord->save(false);
                        $order->id = $orderRecord->id;
                    }else{
                        return false;
                    }

                    craft()->commerce_customers->setLastUsedAddresses($orderRecord->billingAddressId,$orderRecord->shippingAddressId);

                    CommerceDbHelper::commitStackedTransaction();

                    //raising event
                    $event = new Event($this, [
                        'order' => $order
                    ]);
                    $this->onSaveOrder($event);

                    //creating order history record
                    if ($orderRecord->id && $oldStatusId != $orderRecord->orderStatusId) {
                        if (!craft()->commerce_orderHistories->createOrderHistoryFromOrder($order,
                            $oldStatusId)
                        ) {
                            CommercePlugin::log('Error saving order history after Order save.',LogLevel::Error);
                            throw new Exception('Error saving order history');
                        }
                    }

                    return true;
                }
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        CommerceDbHelper::rollbackStackedTransaction();

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
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel)) {
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
        // Don't recalc the totals of completed orders.
        if (!$order->id or $order->dateOrdered != null) {
            return;
        }

        //calculating adjustments
        $lineItems = craft()->commerce_lineItems->getAllLineItemsByOrderId($order->id);

        // reset base totals
        $order->baseDiscount = 0;
        $order->baseShippingCost = 0;
        $order->itemTotal = 0;
        foreach ($lineItems as $key => $item) { //resetting fields calculated by adjusters

            // remove the item from the cart if the purchasable does not exist anymore.
            if(!$lineItems[$key]->purchasableId){
                unset($lineItems[$key]);
                craft()->commerce_lineItems->deleteLineItem($item);
                continue;
            }

            $item->tax = 0;
            $item->taxIncluded = 0;
            $item->shippingCost = 0;
            $item->discount = 0;
            // Need to have an initial itemTotal for use by adjusters.
            $order->itemTotal += $item->getTotal();

        }

        $order->setLineItems($lineItems);

        /** @var Commerce_OrderAdjustmentModel[] $adjustments */
        $adjustments = [];
        foreach ($this->getAdjusters() as $adjuster) {
            $adjustments = array_merge($adjustments,
                $adjuster->adjust($order, $lineItems));
        }

        //refreshing adjustments
        craft()->commerce_orderAdjustments->deleteAllOrderAdjustmentsByOrderId($order->id);

        foreach ($adjustments as $adjustment) {
            $result = craft()->commerce_orderAdjustments->saveOrderAdjustment($adjustment);
            if (!$result) {
                $errors = $adjustment->getAllErrors();
                throw new Exception('Error saving order adjustment: ' . implode(', ',
                        $errors));
            }
        }

        //recalculating order amount and saving items
        $order->itemTotal = 0;
        foreach ($lineItems as $item) {
            $result = craft()->commerce_lineItems->saveLineItem($item);

            $order->itemTotal += $item->total;

            if (!$result) {
                $errors = $item->getAllErrors();
                throw new Exception('Error saving line item: ' . implode(', ',
                        $errors));
            }
        }

        $order->totalPrice = $order->itemTotal + $order->baseDiscount + $order->baseShippingCost;
        $order->totalPrice = max(0, $order->totalPrice);
    }

    /**
     * @return Commerce_AdjusterInterface[]
     */
    private function getAdjusters()
    {
        $adjusters = [
            new Commerce_ShippingAdjuster,
            new Commerce_DiscountAdjuster,
            new Commerce_TaxAdjuster,
        ];

        $additional = craft()->plugins->call('commerce_registerOrderAdjusters');

        foreach ($additional as $additionalAdjusters) {
            $adjusters = array_merge($adjusters, $additionalAdjusters);
        }

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
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel)) {
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

        //raising event on order complete
        $event = new Event($this, [
            'order' => $order
        ]);
        $this->onBeforeOrderComplete($event);

        $order->dateOrdered = DateTimeHelper::currentTimeForDb();
        if ($status = craft()->commerce_orderStatuses->getDefaultOrderStatus()) {
            $order->orderStatusId = $status->id;
        }else{
            throw new Exception(Craft::t('No default Status available to set on completed order.'));
        }


        if($order->getCustomer()->userId && $order->billingAddress){
            $snapShotBillingAddress = Commerce_AddressModel::populateModel($order->billingAddress);
            $snapShotBillingAddress->id = null;
            if(craft()->commerce_addresses->saveAddress($snapShotBillingAddress)){
                $order->billingAddressId = $snapShotBillingAddress->id;
            }else{
                throw new Exception('Error on saving snapshot billing address during order completion: ' . implode(', ',
                        $snapShotBillingAddress->getAllErrors()));
            };
        }

        if($order->getCustomer()->userId && $order->shippingAddress){
            $snapShotShippingAddress = Commerce_AddressModel::populateModel($order->shippingAddress);
            $snapShotShippingAddress->id = null;
            if(craft()->commerce_addresses->saveAddress($snapShotShippingAddress)){
                $order->shippingAddressId = $snapShotShippingAddress->id;
            }else{
                throw new Exception('Error on saving snapshot shipping address during order completion: ' . implode(', ',
                        $snapShotShippingAddress->getAllErrors()));
            };
        }

        if (!$this->saveOrder($order)) {
            return false;
        }

        craft()->commerce_cart->forgetCart();
        craft()->commerce_customers->forgetCustomer();

        //raising event on order complete
        $event = new Event($this, [
            'order' => $order
        ]);
        $this->onOrderComplete($event);

        return true;
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
     * @param Commerce_OrderModel $order
     * @param Commerce_AddressModel $shippingAddress
     * @param Commerce_AddressModel $billingAddress
     *
     * @return bool
     * @throws \Exception
     */
    public function setOrderAddresses(
        Commerce_OrderModel $order,
        Commerce_AddressModel $shippingAddress,
        Commerce_AddressModel $billingAddress
    )
    {
        CommerceDbHelper::beginStackedTransaction();
        try {

            $customerId = craft()->commerce_customers->getCustomerId();
            $currentCustomerAddressIds = craft()->commerce_customers->getAddressIds($customerId);

            // Customers can only set addresses that are theirs
            if ($shippingAddress->id && !in_array($shippingAddress->id, $currentCustomerAddressIds)) {
                return false;
            }
            // Customer can only set addresses that are theirs
            if ($billingAddress->id && !in_array($billingAddress->id, $currentCustomerAddressIds)) {
                return false;
            }

            $result1 = craft()->commerce_customers->saveAddress($shippingAddress);

            if (($billingAddress->id && $billingAddress->id == $shippingAddress->id) || $shippingAddress === $billingAddress) {
                $result2 = true;
            } else {
                $result2 = craft()->commerce_customers->saveAddress($billingAddress);
            }

            $order->setShippingAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);

            if ($result1 && $result2) {

                $order->shippingAddressId = $shippingAddress->id;
                $order->billingAddressId = $billingAddress->id;

                $this->saveOrder($order);
                CommerceDbHelper::commitStackedTransaction();

                return true;
            }
        } catch (\Exception $e) {
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
    public function recalculateOrder(Commerce_OrderModel $order)
    {
        foreach ($order->lineItems as $item) {
            if ($item->refreshFromPurchasable()) {
                if (!craft()->commerce_lineItems->saveLineItem($item)) {
                    throw new Exception('Error on saving line item: ' . implode(', ',
                            $item->getAllErrors()));
                }
            } else {
                craft()->commerce_lineItems->deleteLineItem($item);
            }
        }

        $this->saveOrder($order);
    }

}
