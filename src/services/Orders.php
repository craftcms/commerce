<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\adjusters\Tax;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\commerce\records\Order as OrderRecord;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use yii\base\Component;
use yii\base\Exception;

/**
 * Orders service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Orders extends Component
{

    // Constants
    // =========================================================================

    /**
     * @event OrderEvent The event that is raised before an order is saved.
     */
    const EVENT_BEFORE_SAVE_ORDER = 'beforeSaveOrder';

    /**
     * @event OrderEvent The event that is raised after an order is saved.
     */
    const EVENT_AFTER_SAVE_ORDER = 'afterSaveOrder';

    /**
     * @event OrderEvent This event is raised when an order is completed
     */
    const EVENT_BEFORE_COMPLETE_ORDER = 'beforeCompleteOrder';

    /**
     * @event OrderEvent This event is raised after an order is completed
     */
    const EVENT_AFTER_COMPLETE_ORDER = 'afterCompleteOrder';

    /**
     * @event AdjusterEvent This event is raised when compiling the list of adjusters for an order
     */
    const EVENT_REGISTER_ORDER_ADJUSTERS = 'registerOrderAdjusters';

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return Order|null
     */
    public function getOrderById($id)
    {
        if (!$id) {
            return null;
        }

        $query = Order::find();
        $query->id($id);
        $query->status(null);

        return $query->one();
    }

    /**
     * @param string $number
     *
     * @return Order|null
     */
    public function getOrderByNumber($number)
    {
        $query = Order::find();
        $query->number($number);

        return $query->one();
    }

    /**
     * @param int|Customer $customer
     *
     * @return Order[]|null
     */
    public function getOrdersByCustomer($customer)
    {
        $query = Order::find();
        $query->customer($customer);
        $query->isCompleted(true);
        $query->limit(null);

        return $query->all();
    }

    /**
     * @param string $email
     *
     * @return Order[]
     */
    public function getOrdersByEmail($email)
    {
        $query = Order::find();
        $query->email($email);
        $query->isCompleted(true);
        $query->limit(null);

        return $query->all();
    }

    /**
     * Updates the orders totalPaid and datePaid date and completes order
     *
     * @param Order $order
     */
    public function updateOrderPaidTotal(Order $order)
    {
        $totalPaid = Plugin::getInstance()->getPayments()->getTotalPaidForOrder($order);

        $order->totalPaid = $totalPaid;

        if ($order->isPaid()) {
            if ($order->datePaid == null) {
                $order->datePaid = DateTimeHelper::currentTimeStamp();
            }
        }

        $this->saveOrder($order);

        if (!$order->isCompleted) {
            if ($order->isPaid()) {
                $this->completeOrder($order);
            } else {
                // maybe not paid in full, but authorized enough to complete order.
                $totalAuthorized = Plugin::getInstance()->getPayments()->getTotalAuthorizedForOrder($order);
                if ($totalAuthorized >= $order->totalPrice) {
                    $this->completeOrder($order);
                }
            }
        }
    }

    /**
     * @param Order $order
     *
     * @return bool
     * @throws \Exception
     */
    public function saveOrder($order)
    {
        if (!$order->id) {
            $orderRecord = new OrderRecord();
        } else {
            $orderRecord = OrderRecord::findOne($order->id);

            if (!$orderRecord) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order exists with the ID “{id}”',
                    ['id' => $order->id]));
            }
        }

        // Set default payment method
        if (!$order->paymentMethodId) {
            $methods = Plugin::getInstance()->getPaymentMethods()->getAllFrontEndPaymentMethods();
            if (count($methods)) {
                $order->paymentMethodId = $methods[0]->id;
            }
        }

        // Get the customer ID from the session
        if (!$order->customerId && !Craft::$app->request->isConsoleRequest) {
            $order->customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        }

        // Set default addresses if this is a new cart
        if (!$order->isCompleted && $customer = Plugin::getInstance()->getCustomers()->getCustomerById($order->customerId)) {
            $lastShippingAddressId = $customer->lastUsedShippingAddressId;

            if (!$order->shippingAddressId && $lastShippingAddressId && $address = Plugin::getInstance()->getAddresses()->getAddressById($lastShippingAddressId)) {
                $order->shippingAddressId = $address->id;
            }

            $lastBillingAddressId = $customer->lastUsedBillingAddressId;

            if (!$order->billingAddressId && $lastBillingAddressId && $address = Plugin::getInstance()->getAddresses()->getAddressById($lastBillingAddressId)) {
                $order->billingAddressId = $address->id;
            }
        }

        $order->email = Plugin::getInstance()->getCustomers()->getCustomerById($order->customerId)->email;

        // Will not adjust a completed order, we don't want totals to change.
        $this->calculateAdjustments($order);

        $oldStatusId = $orderRecord->orderStatusId;

        //raising event
        $event = new OrderEvent(['order' => $order]);
        $this->trigger(self::EVENT_BEFORE_SAVE_ORDER, $event);

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

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$order->hasErrors() && Craft::$app->getElements()->saveElement($order)) {
                $orderRecord->id = $order->id;
                $orderRecord->save(false);

                $transaction->commit();

                //raising event
                $event = new OrderEvent(['order' => $order]);
                $this->trigger(self::EVENT_AFTER_SAVE_ORDER, $event);

                //creating order history record
                $hasNewStatus = $orderRecord->id && $oldStatusId != $orderRecord->orderStatusId;

                if ($hasNewStatus && !Plugin::getInstance()->getOrderHistories()->createOrderHistoryFromOrder($order, $oldStatusId)) {
                    Craft::log('Error saving order history after Order save.', __METHOD__);
                    throw new Exception('Error saving order history');
                }

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
    }

    /**
     * @param Order $order
     *
     * @throws Exception
     */
    private function calculateAdjustments(Order $order)
    {
        // Don't recalc the totals of completed orders.
        if (!$order->id || $order->isCompleted) {
            return;
        }

        //calculating adjustments
        $lineItems = Plugin::getInstance()->getLineItems()->getAllLineItemsByOrderId($order->id);

        // reset base totals
        $order->baseDiscount = 0;
        $order->baseShippingCost = 0;
        $order->baseTax = 0;
        $order->itemTotal = 0;
        foreach ($lineItems as $key => $item) {
            if (!$item->refreshFromPurchasable()) {
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
        Plugin::getInstance()->getOrderAdjustments()->deleteAllOrderAdjustmentsByOrderId($order->id);

        // collect new adjustments
        foreach ($this->getAdjusters() as $adjuster) {
            $adjustments = $adjuster->adjust($order, $lineItems);
            $order->setAdjustments(array_merge($order->getAdjustments(), $adjustments));
        }

        // save new adjustment models
        foreach ($order->getAdjustments() as $adjustment) {
            $result = Plugin::getInstance()->getOrderAdjustments()->saveOrderAdjustment($adjustment);
            if (!$result) {
                $errors = $adjustment->errors;
                throw new Exception('Error saving order adjustment: '.implode(', ', $errors));
            }
        }

        //recalculating order amount and saving items
        $order->itemTotal = 0;
        foreach ($lineItems as $item) {
            $result = Plugin::getInstance()->getLineItems()->saveLineItem($item);
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

        if (!$same) {
            Craft::error(['Total of line items after adjustments does not equal total of adjustment amounts plus original sale prices for order #{orderNumber}', ['orderNumber' => $order->number]], __METHOD__);
        }

        $order->totalPrice = Currency::round(max(0, $order->totalPrice));

        // Since shipping adjusters run on the original price, pre discount, let's recalculate
        // if the currently selected shipping method is now not available.
        $availableMethods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($order);
        if ($order->getShippingMethodHandle()) {
            if (!isset($availableMethods[$order->getShippingMethodHandle()]) || empty($availableMethods)) {
                $order->shippingMethod = null;
                $this->calculateAdjustments($order);

                return;
            }
        }
    }

    /**
     * @param Order    $order
     * @param LineItem $lineItem
     *
     * @return bool
     */
    public function removeLineItemFromOrder(Order $order, LineItem $lineItem)
    {
        $success = false;
        $lineItems = $order->getLineItems();
        foreach ($lineItems as $key => $item) {
            if ($lineItem->id == $item->id) {
                if ($lineItem->id == $item->id) {
                    $lineItem = LineItemRecord::findOne($lineItem->id);

                    if ($lineItem && $lineItem->delete()) {
                        $success = true;
                        unset($lineItems[$key]);
                        $order->setLineItems($lineItems);
                    }
                }
            }
        }

        return $success;
    }

    /**
     * @return AdjusterInterface[]
     */
    private function getAdjusters(): array
    {
        $adjusters = [
            Shipping::class,
            Discount::class,
            Tax::class
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $adjusters
        ]);
        $this->trigger(self::EVENT_REGISTER_ORDER_ADJUSTERS, $event);

        return $event->types;
    }

    /**
     * Completes an Order
     *
     * @param Order $order
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function completeOrder(Order $order): bool
    {

        if ($order->isCompleted) {
            return true;
        }

        $order->isCompleted = true;
        $order->dateOrdered = Db::prepareDateForDb(new \DateTime());
        $order->orderStatusId = Plugin::getInstance()->getOrderStatuses()->getDefaultOrderStatusId();

        //raising event on order complete
        $event = new OrderEvent(['order' => $order]);
        $this->trigger(self::EVENT_BEFORE_COMPLETE_ORDER, $event);

        if ($this->saveOrder($order)) {
            // Run order complete handlers directly.
            Plugin::getInstance()->getDiscounts()->orderCompleteHandler($order);
            Plugin::getInstance()->getVariants()->orderCompleteHandler($order);
            Plugin::getInstance()->getCustomers()->orderCompleteHandler($order);

            //raising event on order complete
            $event = new OrderEvent(['order' => $order]);
            $this->trigger(self::EVENT_AFTER_COMPLETE_ORDER, $event);

            return true;
        }

        Plugin::log(Craft::t('commerce', 'commerce', 'Could not mark order {number} as complete. Order save failed during order completion with errors: {errors}',
            ['number' => $order->number, 'order' => json_encode($order->errors)]), LogLevel::Error, true);

        return false;
    }

    /**
     * Save and set the given addresses to the current cart/order
     *
     * @param Order   $order
     * @param Address $shippingAddress
     * @param Address $billingAddress
     * @param string  $error
     *
     * @return bool
     * @throws \Exception
     */
    public function setOrderAddresses(
        Order $order,
        Address $shippingAddress,
        Address $billingAddress,
        &$error = ''
    ) {

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$order->id) {
                if (!$this->saveOrder($order)) {
                    Db::rollbackStackedTransaction();
                    throw new Exception(Craft::t('commerce', 'commerce', 'Error on creating empty cart'));
                }
            }

            $customerId = $order->customerId;
            $currentCustomerAddressIds = Plugin::getInstance()->getCustomers()->getAddressIds($customerId);

            $ownAddress = true;
            // Customers can only set addresses that are theirs
            if ($shippingAddress->id && !in_array($shippingAddress->id, $currentCustomerAddressIds)) {
                $ownAddress = false;
            }
            // Customer can only set addresses that are theirs
            if ($billingAddress->id && !in_array($billingAddress->id, $currentCustomerAddressIds)) {
                $ownAddress = false;
            }

            if (!$ownAddress) {
                $error = Craft::t('commerce', 'commerce', 'Can not choose an address ID that does not belong to the customer.');
            }

            $result1 = Plugin::getInstance()->getCustomers()->saveAddress($shippingAddress);

            if (($billingAddress->id && $billingAddress->id == $shippingAddress->id) || $shippingAddress === $billingAddress) {
                $result2 = true;
            } else {
                $result2 = Plugin::getInstance()->getCustomers()->saveAddress($billingAddress);
            }

            $order->setShippingAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);

            if ($result1 && $result2) {

                $order->shippingAddressId = $shippingAddress->id;
                $order->billingAddressId = $billingAddress->id;

                $this->saveOrder($order);
                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
    }
}
