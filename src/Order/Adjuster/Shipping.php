<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster;

use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Models\ShippingRule;

/**
 * Shipping Adjustments
 */
class Shipping implements AdjusterInterface
{
    public const ADJUSTMENT_TYPE = 'shipping';

    private Order $_order;

    private bool $_isEstimated = false;

    /**
     * Temporary feature flag for testing
     */
    private bool $_consolidateShippingToSingleAdjustment = false;

    #[\Override]
    public function adjust(Order $order): array
    {
        $this->_order = $order;
        $this->_isEstimated = (!$order->shippingAddressId && $order->estimatedShippingAddressId);

        if (!$order->shippingMethodHandle) {
            return [];
        }

        $matchingMethods = Plugin::getInstance()->getShippingMethods()->getMatchingShippingMethods($order);
        $shippingMethod = $matchingMethods[$order->shippingMethodHandle] ?? null;
        $lineItems = $order->getLineItems();

        if ($shippingMethod === null) {
            return [];
        }

        $nonShippableItems = [];

        foreach ($lineItems as $item) {
            if (!$item->getIsShippable()) {
                $nonShippableItems[$item->id] = $item->id;
            }
        }

        // Are all line items non shippable items? No shipping cost.
        if (count($lineItems) == count($nonShippableItems)) {
            return [];
        }

        $adjustments = [];

        $discounts = Plugin::getInstance()->getDiscounts()->getAllActiveDiscounts($order);

        // Check to see if we have shipping related discounts
        $hasOrderLevelShippingRelatedDiscounts = (bool)ArrayHelper::firstWhere($discounts, 'hasFreeShippingForOrder', true, false);
        $hasLineItemLevelShippingRelatedDiscounts = (bool)ArrayHelper::firstWhere($discounts, 'hasFreeShippingForMatchingItems', true, false);

        /** @var ShippingRule|null $rule */
        $rule = $shippingMethod->getMatchingShippingRule($this->_order);
        if ($rule) {
            $itemTotalAmount = 0;

            // Check for order level discounts for shipping
            $hasDiscountRemoveShippingCosts = false;
            if ($hasOrderLevelShippingRelatedDiscounts) {
                foreach ($discounts as $discount) {
                    $matchedOrder = Plugin::getInstance()->getDiscounts()->matchOrder($this->_order, $discount);

                    if ($discount->hasFreeShippingForOrder && $matchedOrder) {
                        $hasDiscountRemoveShippingCosts = true;
                        break;
                    }

                    if ($matchedOrder && $discount->stopProcessing) {
                        break;
                    }
                }
            }

            if (!$hasDiscountRemoveShippingCosts) {
                //checking items shipping categories
                foreach ($order->getLineItems() as $item) {
                    // Lets match the discount now for free shipped items and not even make a shipping cost for the line item.
                    $hasFreeShippingFromDiscount = false;
                    if ($hasLineItemLevelShippingRelatedDiscounts) {
                        foreach ($discounts as $discount) {
                            $matchedLineItem = Plugin::getInstance()->getDiscounts()->matchLineItem($item, $discount, true);

                            if ($discount->hasFreeShippingForMatchingItems && $matchedLineItem) {
                                $hasFreeShippingFromDiscount = true;
                                break;
                            }

                            if ($matchedLineItem && $discount->stopProcessing) {
                                break;
                            }
                        }
                    }

                    $lineItemHasFreeShipping = $item->getHasFreeShipping();
                    $shippable = $item->getIsShippable();

                    if (!$lineItemHasFreeShipping && !$hasFreeShippingFromDiscount && $shippable) {
                        $adjustment = $this->_createAdjustment($shippingMethod, $rule);

                        $percentageRate = $rule->getPercentageRate($item->shippingCategoryId);
                        $perItemRate = $rule->getPerItemRate($item->shippingCategoryId);
                        $weightRate = $rule->getWeightRate($item->shippingCategoryId);

                        $percentageAmount = $item->getSubtotal() * $percentageRate;
                        $perItemAmount = $item->qty * $perItemRate;
                        $weightAmount = ($item->weight * $item->qty) * $weightRate;

                        $adjustment->amount = Currency::round($percentageAmount + $perItemAmount + $weightAmount);
                        $adjustment->setLineItem($item);
                        if ($adjustment->amount) {
                            $adjustments[] = $adjustment;
                        }
                        $itemTotalAmount += $adjustment->amount;
                    }
                }

                $baseAmount = Currency::round($rule->getBaseRate());
                if ($baseAmount && $baseAmount != 0) {
                    $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                    $adjustment->amount = $baseAmount;
                    $adjustments[] = $adjustment;
                }

                $adjustmentToMinimumAmount = 0;
                // Is there a minimum rate and is the total shipping cost currently below it?
                if ($rule->getMinRate() != 0 && (($itemTotalAmount + $baseAmount) < Currency::round($rule->getMinRate()))) {
                    $adjustmentToMinimumAmount = Currency::round($rule->getMinRate()) - ($itemTotalAmount + $baseAmount);
                    $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                    $adjustment->amount = $adjustmentToMinimumAmount;
                    $adjustment->description .= ' Adjusted to minimum rate';
                    $adjustments[] = $adjustment;
                }

                if ($rule->getMaxRate() != 0 && (($itemTotalAmount + $baseAmount + $adjustmentToMinimumAmount) > Currency::round($rule->getMaxRate()))) {
                    $adjustmentToMaxAmount = Currency::round($rule->getMaxRate()) - ($itemTotalAmount + $baseAmount + $adjustmentToMinimumAmount);
                    $adjustment = $this->_createAdjustment($shippingMethod, $rule);
                    $adjustment->amount = $adjustmentToMaxAmount;
                    $adjustment->description .= ' Adjusted to maximum rate';
                    $adjustments[] = $adjustment;
                }
            }
        }

        // Might be a shipping method that matches but does not have any shipping rules.
        if ($rule === null) {
            $adjustment = new OrderAdjustment();
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->setOrder($this->_order);
            $adjustment->name = $shippingMethod->getName();
            $adjustment->description = '';
            $adjustment->isEstimated = $this->_isEstimated;
            $adjustment->sourceSnapshot = [
                'shippingMethodHandle' => $shippingMethod->getHandle(),
                'shippingMethodId' => $shippingMethod->getId(),
                'shippingMethodName' => $shippingMethod->getName(),
                'shippingMethodType' => $shippingMethod->getType(),
            ];
            $adjustment->amount = Currency::round($shippingMethod->getPriceForOrder($this->_order), $this->_order->getStore()->getCurrency());
            $adjustments[] = $adjustment;
        }

        if ($this->_consolidateShippingToSingleAdjustment) {
            $amount = 0;
            foreach ($adjustments as $adjustment) {
                $amount += $adjustment->amount;
            }

            //preparing model
            $adjustment = new OrderAdjustment();
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->setOrder($this->_order);
            $adjustment->name = $shippingMethod->getName();
            $adjustment->amount = $amount;
            $adjustment->description = $rule->getDescription();
            $adjustment->isEstimated = $this->_isEstimated;
            $adjustment->setSourceSnapshot([]);

            return [$adjustment];
        }

        return $adjustments;
    }

    private function _createAdjustment(ShippingMethodInterface $shippingMethod, ShippingRule $rule): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->setOrder($this->_order);
        $adjustment->name = $shippingMethod->getName();
        $adjustment->description = $rule->getDescription();
        $adjustment->isEstimated = $this->_isEstimated;
        $adjustment->sourceSnapshot = $rule->toArray();

        return $adjustment;
    }
}
