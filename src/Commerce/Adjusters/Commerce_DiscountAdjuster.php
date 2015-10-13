<?php

namespace Commerce\Adjusters;

use Craft\Commerce_DiscountModel;
use Craft\Commerce_LineItemModel;
use Craft\Commerce_OrderAdjustmentModel;
use Craft\Commerce_OrderModel;

/**
 * Discount Adjustments
 *
 * Class Commerce_DiscountAdjuster
 *
 * @package Commerce\Adjusters
 */
class Commerce_DiscountAdjuster implements Commerce_AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Discount';

    /**
     * @param Commerce_OrderModel $order
     * @param Commerce_LineItemModel[] $lineItems
     *
     * @return \Craft\Commerce_OrderAdjustmentModel[]
     */
    public function adjust(Commerce_OrderModel &$order, array $lineItems = [])
    {
        if (empty($lineItems)) {
            return [];
        }

        $discount = \Craft\craft()->commerce_discounts->getByCode($order->couponCode);
        if (!$discount->id) {
            return [];
        }

        if ($adjustment = $this->getAdjustment($order, $lineItems, $discount)) {
            return [$adjustment];
        } else {
            return [];
        }
    }

    /**
     * @param Commerce_OrderModel $order
     * @param Commerce_LineItemModel[] $lineItems
     * @param Commerce_DiscountModel $discount
     *
     * @return Commerce_OrderAdjustmentModel|false
     */
    private function getAdjustment(Commerce_OrderModel $order, array $lineItems, Commerce_DiscountModel $discount)
    {
        //preparing model
        $adjustment = new Commerce_OrderAdjustmentModel;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $discount->name;
        $adjustment->orderId = $order->id;
        $adjustment->description = $this->getDescription($discount);
        $adjustment->optionsJson = $discount->attributes;

        //checking items
        $matchingQty = 0;
        $matchingTotal = 0;
        foreach ($lineItems as $item) {
            if (\Craft\craft()->commerce_discounts->matchLineItem($item, $discount)) {
                $matchingQty += $item->qty;
                $matchingTotal += $item->getSubtotalWithSale();
            }
        }

        if (!$matchingQty) {
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

        foreach ($lineItems as $item) {
            $item->discount = $discount->perItemDiscount * $item->qty + $discount->percentDiscount * $item->getSubtotalWithSale();
            // If the discount is larger than the subtotal
            // make the discount equal the discount, thus making the item free.
            if (($item->discount * -1) > $item->getSubtotalWithSale()) {
                $item->discount = -$item->getSubtotalWithSale();
            }

            if (!$item->purchasable->product->promotable) {
                $item->discount = 0;
            }

            if ($discount->freeShipping) {
                $item->shippingCost = 0;
            }
        }

        if ($discount->freeShipping) {
            $order->baseShippingCost = 0;
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
     * @param Commerce_DiscountModel $discount
     *
     * @return string "1$ and 5% per item and 10$ base rate"
     */
    private function getDescription(Commerce_DiscountModel $discount)
    {
        $description = '';
        if ($discount->perItemDiscount || $discount->percentDiscount) {
            if ($discount->perItemDiscount) {
                $description .= $discount->perItemDiscount * 1 . '$ ';
            }

            if ($discount->percentDiscount) {
                if ($discount->perItemDiscount) {
                    $description .= 'and ';
                }

                $description .= $discount->percentDiscount * 100 . '% ';
            }

            $description .= 'per item ';
        }

        if ($discount->baseDiscount) {
            if ($description) {
                $description .= 'and ';
            }
            $description .= $discount->baseDiscount * 1 . '$ base rate ';
        }

        if ($discount->freeShipping) {
            if ($description) {
                $description .= 'and ';
            }

            $description .= 'free shipping ';
        }

        return $description;
    }
}