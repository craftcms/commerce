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

        $discount = \Craft\craft()->market_discount->getByCode($order->couponCode);
        if(!$discount->id) {
            return [];
        }

        if($adjustment = $this->getAdjustment($order, $lineItems, $discount)) {
            return [$adjustment];
        } else {
            return [];
        }
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
        $adjustment->description = $this->getDescription($discount);
        $adjustment->optionsJson = $discount->attributes;

        //checking items
        $matchingQty = 0;
        $matchingTotal = 0;
        foreach($lineItems as $item) {
            if(\Craft\craft()->market_discount->matchLineItem($item, $discount)) {
                $matchingQty += $item->qty;
                $matchingTotal += $item->getSubtotalWithSale();
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

        foreach($lineItems as $item) {
            $item->discountAmount = $discount->perItemDiscount * $item->qty + $discount->percentDiscount * $item->getSubtotalWithSale();
            if($discount->freeShipping) {
                $item->shippingAmount = 0;
            }
        }

        if ($discount->freeShipping) {
            $order->baseShippingRate = 0;
        }

        $order->baseDiscount = $discount->baseDiscount;

        // only display adjustment if an amount was calculated
        if ($amount) {
            $adjustment->amount = $amount;
            return $adjustment;
        } else {
            return false;
        }
    }

    /**
     * @param Market_DiscountModel $discount
     * @return string "1$ and 5% per item and 10$ base rate"
     */
    private function getDescription(Market_DiscountModel $discount)
    {
        $description = '';
        if($discount->perItemDiscount || $discount->percentDiscount) {
            if($discount->perItemDiscount) {
                $description .= $discount->perItemDiscount*1 . '$ ';
            }

            if($discount->percentDiscount) {
                if($discount->perItemDiscount) {
                    $description .= 'and ';
                }

                $description .= $discount->percentDiscount*1 . '% ';
            }

            $description .= 'per item ';
        }

        if($discount->baseDiscount) {
            if($description) {
                $description .= 'and ';
            }
            $description .= $discount->baseDiscount*1 . '$ base rate ';
        }

        if($discount->freeShipping) {
            if($description) {
                $description .= 'and ';
            }

            $description .= 'free shipping ';
        }

        return $description;
    }
}