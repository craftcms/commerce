<?php

namespace craft\commerce\adjusters;

use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Discount as DiscountModel;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;

/**
 * Discount Adjuster
 *
 * @package Commerce\Adjusters
 */
class Discount implements AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Discount';

    /**
     * @param \craft\commerce\elements\Order    $order
     * @param \craft\commerce\models\LineItem[] $lineItems
     *
     * @return \craft\commerce\models\OrderAdjustment[]
     */
    public function adjust(Order &$order, array $lineItems = [])
    {
        if (empty($lineItems)) {
            return [];
        }

        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();

        // Find discounts with no coupon or the coupon that matches the order.
        $availableDiscounts = [];
        foreach ($discounts as $discount)
        {
            if ($discount->code == null)
            {
                $availableDiscounts[] = $discount;
            }

            if ($order->couponCode && ($discount->code == $order->couponCode))
            {
                $availableDiscounts[] = $discount;
            }
        }

        $adjustments = [];
        foreach ($availableDiscounts as $discount) {
            if ($adjustment = $this->getAdjustment($order, $lineItems, $discount)) {
                $adjustments[] = $adjustment;

                if ($discount->stopProcessing) {
                    break;
                }
            }
        }

        return $adjustments;
    }

    /**
     * @param Order         $order
     * @param LineItem[]    $lineItems
     * @param DiscountModel $discount
     *
     * @return OrderAdjustment|false
     */
    private function getAdjustment(Order $order, array $lineItems, DiscountModel $discount)
    {
        //preparing model
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $discount->name;
        $adjustment->orderId = $order->id;
        $adjustment->description = $discount->description;
        $adjustment->optionsJson = $discount->attributes;
        $affectedLineIds = [];


        // Handle special coupon rules
        if (strcasecmp($order->couponCode, $discount->code) == 0)
        {
            // Since we will allow the coupon to be added to an anonymous cart with no email, we need to remove it
            // if a limit has been set.
            if ($order->email && $discount->perEmailLimit) {
                $previousOrders = Plugin::getInstance()->getOrders()->getOrdersByEmail($order->email);

                $usedCount = 0;

                foreach ($previousOrders as $previousOrder) {
                    if (strcasecmp($previousOrder->couponCode, $discount->code) == 0)
                        $usedCount += 1;
                    }
                }

                if ($usedCount >= $discount->perEmailLimit) {
                    $order->couponCode = '';

                    return false;
                }
            }
        }


        $now = new \DateTime();
        $from = $discount->dateFrom;
        $to = $discount->dateTo;
        if ($from && $from > $now || $to && $to < $now) {
            return false;
        }

        //checking items
        $matchingQty = 0;
        $matchingTotal = 0;
        $matchingLineIds = [];
        foreach ($lineItems as $item) {
            if (Plugin::getInstance()->getDiscounts()->matchLineItem($item, $discount)) {
                $matchingLineIds[] = $item->id;
                $matchingQty += $item->qty;
                $matchingTotal += $item->getSubtotal();
            }
        }

        if (!$matchingQty) {
            return false;
        }

        // Have they entered a max qty?
        if ($discount->maxPurchaseQty > 0) {
            if ($matchingQty > $discount->maxPurchaseQty) {
                return false;
            }
        }

        // Reject if they have not added enough matching items
        if ($matchingQty < $discount->purchaseQty) {
            return false;
        }

        // Reject if the matching items values is not enough
        if ($matchingTotal < $discount->purchaseTotal) {
            return false;
        }

        $amount = $discount->baseDiscount;
        $shippingRemoved = 0;

        foreach ($lineItems as $item) {
            if (in_array($item->id, $matchingLineIds)) {
                $amountPerItem = Currency::round($discount->perItemDiscount * $item->qty);
                $amountPercentage = Currency::round($discount->percentDiscount * $item->getSubtotal());

                $amount += $amountPerItem + $amountPercentage;
                $item->discount += $amountPerItem + $amountPercentage;

                // If the discount is now larger than the subtotal only make the discount amount the same as the total of the line.
                if (($item->discount * -1) > $item->getSubtotal()) {
                    $diff = ($item->discount * -1) - $item->getSubtotal();
                    $item->discount = -$item->getSubtotal();
                    // Make sure the adjustment amount is reduced by the amount we modified the discount by
                    // due to it being too large.
                    $amount = $amount + $diff;
                }

                $affectedLineIds[] = $item->id;

                if ($discount->freeShipping) {
                    $shippingRemoved = $shippingRemoved + $item->shippingCost;
                    $item->shippingCost = 0;
                }
            }
        }

        if ($discount->freeShipping) {
            $shippingRemoved = $shippingRemoved + $order->baseShippingCost;
            $order->baseShippingCost = 0;
        }

        $order->baseDiscount += $discount->baseDiscount;

        // only display adjustment if an amount was calculated
        if ($amount || $shippingRemoved) {
            // Record which line items this discount affected.
            $adjustment->optionsJson = array_merge(['lineItemsAffected' => $affectedLineIds], $adjustment->optionsJson);
            $adjustment->amount = $amount + ($shippingRemoved * -1);

            return $adjustment;
        }

        return false;
    }
}
