<?php

namespace Market\Adjusters;

use Craft\Market_AddressModel;
use Craft\Market_DiscountModel;
use Craft\Market_LineItemModel;
use Craft\Market_LineItemRecord;
use Craft\Market_OrderAdjustmentModel;
use Craft\Market_OrderModel;
use Craft\Market_DiscountRateModel;

/**
 * Discount Adjustments
 *
 * Class Market_DiscountAdjuster
 * @package Market\Adjusters
 */
class Market_DiscountAdjuster implements Market_AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Discount';

    /**
     * @param Market_OrderModel $order
     * @param Market_LineItemModel[] $lineItems
     * @return \Craft\Market_OrderAdjustmentModel[]
     */
    public function adjust(Market_OrderModel &$order, array $lineItems = [])
    {
        if(empty($lineItems)) {
            return [];
        }

        $adjustments = [];
        $discounts = \Craft\craft()->market_discount->getForItems($lineItems);

        foreach ($discounts as $discount) {
            if($adjustment = $this->getAdjustment($order, $lineItems, $discount)) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @param Market_OrderModel $order
     * @param Market_LineItemModel[] $lineItems
     * @param Market_DiscountModel $discount
     * @return Market_OrderAdjustmentModel|false
     */
    private function getAdjustment(Market_OrderModel $order, array $lineItems, Market_DiscountModel $discount)
    {
        //preparing model
        $adjustment = new Market_OrderAdjustmentModel;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $discount->name;
        $adjustment->orderId = $order->id;
        $adjustment->rate = 0;

        //checking items
        $matchingQty = 0;
        $matchingTotal = 0;
        foreach($lineItems as $item) {
            if(\Craft\craft()->market_discount->matchLineItem($item, $discount)) {
                $matchingQty += $item->qty;
                $matchingTotal += $item->subtotal;
            }
        }

        if(!$matchingQty) {
            return false;
        }

        if ($matchingQty < $discount->purchaseQty) {
            return false;
        }

        if ($matchingTotal < $discount->purchaseTotal) {
            return false;
        }

        // calculate discount (adjustment amount should be negative)
        $amount = $discount->baseDiscount;
        $amount += $discount->perItemDiscount * $matchingQty;
        $amount += $discount->percentDiscount * $matchingTotal;

        if ($discount->freeShipping) {
            //@TODO set free shipping
        }

        // only display adjustment if an amount was calculated
        if ($amount) {
            $adjustment->amount = $amount;
            return $adjustment;
        } else {
            return false;
        }
    }
}