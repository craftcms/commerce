<?php

namespace Commerce\Adjusters;

use Craft\Commerce_LineItemModel;
use Craft\Commerce_OrderAdjustmentModel;
use Craft\Commerce_OrderModel;
use Craft\Commerce_ShippingRuleModel;
use Craft\Commerce_VariantModel;

/**
 * Tax Adjustments
 *
 * Class Commerce_ShippingAdjuster
 *
 * @package Commerce\Adjusters
 */
class Commerce_ShippingAdjuster implements Commerce_AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Shipping';

    /**
     * @param Commerce_OrderModel $order
     * @param Commerce_LineItemModel[] $lineItems
     *
     * @return \Craft\Commerce_OrderAdjustmentModel[]
     */
    public function adjust(Commerce_OrderModel &$order, array $lineItems = [])
    {
        $shippingMethods = \Craft\craft()->commerce_shippingMethods->getAll();

        foreach ($shippingMethods as $method) {
            if($method->getIsEnabled() == true && $method->getHandle() == $order->getShippingMethodHandle()){
                $shippingMethod = $method;
            }
        }

        if (!$shippingMethod) {
            return [];
        }

        $adjustments = [];

        if ($rule = \Craft\craft()->commerce_shippingMethods->getMatchingRule($order, $shippingMethod)) {
            //preparing model
            $adjustment = new Commerce_OrderAdjustmentModel;
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->name = $shippingMethod->name;
            $adjustment->description = $rule->getDescription();
            $adjustment->orderId = $order->id;
            $adjustment->optionsJson = $rule->getOptions();

            //checking items tax categories
            $weight = $qty = $price = 0;
            $itemShippingTotal = 0;
            $freeShippingAmount = 0;
            foreach ($lineItems as $item) {
                $weight += $item->qty * $item->weight;
                $qty += $item->qty;
                $price += $item->getSubtotalWithSale();

                $item->shippingCost = ($item->getSubtotalWithSale() * $rule->getPercentageRate()) + ($rule->getPerItemRate() * $item->qty) + ($item->weight * $rule->getWeightRate());
                $itemShippingTotal += $item->shippingCost;

                if ($item->purchasable->hasFreeShipping()) {
                    $freeShippingAmount += $item->shippingCost;
                    $item->shippingCost = 0;
                }

            }

            //amount for displaying in adjustment
            $amount = $rule->getBaseRate() + $itemShippingTotal - $freeShippingAmount;
            $amount = max($amount, $rule->getMinRate() * 1);

            if ($rule->getMaxRate() * 1) {
                $amount = min($amount, $rule->getMaxRate() * 1);
            }

            $adjustment->amount = $amount;

            //real shipping base rate (can be a bit artificial because it counts min and max rate as well, but in general it equals to be $rule->baseRate)
            $order->baseShippingCost = $amount - ($itemShippingTotal - $freeShippingAmount);

            $adjustments[] = $adjustment;
        }

        return $adjustments;
    }

}
