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
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_OrderService extends BaseApplicationComponent
{
    /**
     * @param Market_OrderModel $order
     *
     * @throws Exception
     */
    private function calculateAdjustments(Market_OrderModel $order)
    {
        // Don't recalc the totals of completed orders.
        if (!$order->id or $order->completedAt != null) {
            return;
        }

        //calculating adjustments
        $lineItems = craft()->market_lineItem->getAllByOrderId($order->id);

        foreach ($lineItems as $item) { //resetting fields calculated by adjusters
            $item->tax      = 0;
            $item->shippingCost = 0;
            $item->discountAmount = 0;
        }

        /** @var Market_OrderAdjustmentModel[] $adjustments */
        $adjustments = [];
        foreach ($this->getAdjusters() as $adjuster) {
            $adjustments = array_merge($adjustments,
                $adjuster->adjust($order, $lineItems));
        }

        //refreshing adjustments
        craft()->market_orderAdjustment->deleteAllByOrderId($order->id);

        foreach ($adjustments as $adjustment) {
            $result = craft()->market_orderAdjustment->save($adjustment);
            if (!$result) {
                $errors = $adjustment->getAllErrors();
                throw new Exception('Error saving order adjustment: ' . implode(', ',
                        $errors));
            }
        }

        //recalculating order amount and saving items
        $order->itemTotal = 0;
        foreach ($lineItems as $item) {
            $result = craft()->market_lineItem->save($item);

            $order->itemTotal += $item->total;

            if (!$result) {
                $errors = $item->getAllErrors();
                throw new Exception('Error saving line item: ' . implode(', ',
                        $errors));
            }
        }

        $order->finalPrice = $order->itemTotal + $order->baseDiscount + $order->baseShippingRate;
        $order->finalPrice = max(0, $order->finalPrice);
    }

    /**
     * Completes an Order
     *
     * @param Market_OrderModel $order
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function complete(Market_OrderModel $order)
    {
        $order->completedAt = DateTimeHelper::currentTimeForDb();
        if ($status = craft()->market_orderStatus->getDefault()) {
            $order->orderStatusId = $status->id;
        }

        if (!$this->save($order)) {
            return false;
        }

        craft()->market_cart->forgetCart($order);

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
     * @return Market_OrderModel
     */
    public function getById($id)
    {
        return craft()->elements->getElementById($id, 'Market_Order');
    }

    /**
     * @param string $number
     *
     * @return Market_OrderModel
     */
    public function getByNumber($number)
    {
        $criteria = craft()->elements->getCriteria('Market_Order');
        $criteria->number = $number;
        return $criteria->first();
    }

    /**
     * @param Market_OrderModel $order
     *
     * @return bool
     * @throws \CDbException
     */
    public function delete($order)
    {
        return craft()->elements->deleteElementById($order->id);
    }

    /**
     * @param Market_OrderModel $order
     *
     * @return bool
     * @throws \Exception
     */
    public function save($order)
    {
        if (!$order->completedAt) {
            //raising event
            $event = new Event($this, [
                'order' => $order
            ]);
            $this->onBeforeSaveOrder($event);
        }

        if (!$order->id) {
            $orderRecord = new Market_OrderRecord();
        } else {
            $orderRecord = Market_OrderRecord::model()->findById($order->id);

            if (!$orderRecord) {
                throw new Exception(Craft::t('No order exists with the ID “{id}”',
                    ['id' => $order->id]));
            }
        }

        // Set default shipping method
        if (!$order->shippingMethodId) {
            $method = craft()->market_shippingMethod->getDefault();
            if ($method) {
                $order->shippingMethodId = $method->id;
            }
        }

        // Set default payment method
        if (!$order->paymentMethodId) {
            $methods = craft()->market_paymentMethod->getAllForFrontend();
            if ($methods) {
                $order->paymentMethodId = $methods[0]->id;
            }
        }

        if (!$order->customerId){
            $order->customerId = craft()->market_customer->getCustomerId();
        }else{
            // if there is no email set and we have a customer, get their email.
            if(!$order->email){
                $order->email = craft()->market_customer->getById($order->customerId)->email;
            }
        }

        // Will not adjust a completed order, we don't want totals to change.
        $this->calculateAdjustments($order);

        $oldStatusId = $orderRecord->orderStatusId;

        $orderRecord->number            = $order->number;
        $orderRecord->itemTotal         = $order->itemTotal;
        $orderRecord->email             = $order->email;
        $orderRecord->completedAt       = $order->completedAt;
        $orderRecord->paidAt            = $order->paidAt;
        $orderRecord->billingAddressId  = $order->billingAddressId;
        $orderRecord->shippingAddressId = $order->shippingAddressId;
        $orderRecord->shippingMethodId  = $order->shippingMethodId;
        $orderRecord->paymentMethodId   = $order->paymentMethodId;
        $orderRecord->orderStatusId     = $order->orderStatusId;
        $orderRecord->couponCode        = $order->couponCode;
        $orderRecord->baseDiscount      = $order->baseDiscount;
        $orderRecord->baseShippingRate  = $order->baseShippingRate;
        $orderRecord->finalPrice        = $order->finalPrice;
        $orderRecord->paidTotal         = $order->paidTotal;
        $orderRecord->customerId        = $order->customerId;
        $orderRecord->returnUrl         = $order->returnUrl;
        $orderRecord->cancelUrl         = $order->cancelUrl;
        $orderRecord->message           = $order->message;
        $orderRecord->shippingAddressData = $order->shippingAddressData;
        $orderRecord->billingAddressData = $order->billingAddressData;

        $orderRecord->validate();
        $order->addErrors($orderRecord->getErrors());

        MarketDbHelper::beginStackedTransaction();

        try {
            if (!$order->hasErrors()) {
                if (craft()->elements->saveElement($order)) {
                    //creating order history record
                    if ($orderRecord->id && $oldStatusId != $orderRecord->orderStatusId) {
                        if (!craft()->market_orderHistory->createFromOrder($order,
                            $oldStatusId)
                        ) {
                            throw new Exception('Error saving order history');
                        }
                    }

                    //saving order record
                    $orderRecord->id = $order->id;
                    $orderRecord->save(false);

                    MarketDbHelper::commitStackedTransaction();

                    //raising event
                    if (!$order->completedAt) {
                        $event = new Event($this, [
                            'order' => $order
                        ]);
                        $this->onSaveOrder($event);
                    }

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
     * Updates the orders paidTotal and paidAt date and completes order
     *
     * @param Market_OrderModel $order
     */
    public function updateOrderPaidTotal(Market_OrderModel $order)
    {
        $totalPaid = craft()->market_payment->getTotalPaidForOrder($order);

        $order->paidTotal = $totalPaid;

        if($order->isPaid()){
            if($order->paidAt == null){
                $order->paidAt = DateTimeHelper::currentTimeForDb();
            }
        }

        $this->save($order);

        if(!$order->completedAt){
            if($order->isPaid()){
                craft()->market_order->complete($order);
            }else{
                // maybe not paid in full, but authorized enough to complete order.
                $totalAuthorized = craft()->market_payment->getTotalAuthorizedForOrder($order);
                if($totalAuthorized >= $order->finalPrice){
                    craft()->market_order->complete($order);
                }
            }
        }
    }

    /**
     * Save and set the given addresses to the current cart/order
     *
     * @param Market_OrderModel   $order
     * @param Market_AddressModel $shippingAddress
     * @param Market_AddressModel $billingAddress
     *
     * @return bool
     * @throws \Exception
     */
    public function setAddresses(
        Market_OrderModel $order,
        Market_AddressModel $shippingAddress,
        Market_AddressModel $billingAddress
    ) {
        MarketDbHelper::beginStackedTransaction();
        try {
            $result1 = craft()->market_customer->saveAddress($shippingAddress);

            if ($billingAddress->id && $billingAddress->id == $shippingAddress->id) {
                $result2 = true;
            } else {
                $result2 = craft()->market_customer->saveAddress($billingAddress);
            }

            if ($result1 && $result2) {

                $order->shippingAddressId = $shippingAddress->id;
                $order->billingAddressId  = $billingAddress->id;

                $order->shippingAddressData = JsonHelper::encode($shippingAddress->attributes);
                $order->billingAddressData = JsonHelper::encode($billingAddress->attributes);

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
     * Full order recalculation
     *
     * @param Market_OrderModel $order
     *
     * @throws Exception
     * @throws \Exception
     */
    public function recalculate(Market_OrderModel $order)
    {
        foreach ($order->lineItems as $item) {
            if ($item->refreshFromPurchasable()) {
                if (!craft()->market_lineItem->save($item)) {
                    throw new Exception('Error on saving lite item: ' . implode(', ',
                            $item->getAllErrors()));
                }
            } else {
                craft()->market_lineItem->delete($item);
            }
        }

        $this->save($order);
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
     * Event: before saving incomplete order
     * Event params: order(Market_OrderModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSaveOrder(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Market_OrderModel)) {
            throw new Exception('onBeforeSaveOrder event requires "order" param with OrderModel instance');
        }
        $this->raiseEvent('onBeforeSaveOrder', $event);
    }

    /**
     * Event: before successful saving incomplete order
     * Event params: order(Market_OrderModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveOrder(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Market_OrderModel)) {
            throw new Exception('onSaveOrder event requires "order" param with OrderModel instance');
        }
        $this->raiseEvent('onSaveOrder', $event);
    }

}
