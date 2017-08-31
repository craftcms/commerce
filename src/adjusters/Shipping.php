<?php

namespace craft\commerce\adjusters;

use craft\commerce\base\AdjusterInterface;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;

/**
 * Tax Adjustments
 *
 * @package Commerce\Adjusters
 */
class Shipping implements AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Shipping';

    /**
     * @param Order      $order
     * @param LineItem[] $lineItems
     *
     * @return OrderAdjustment[]
     */
    public function adjust(Order &$order, array $lineItems = [])
    {
        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($order);

        $shippingMethod = null;

        /** @var ShippingMethod $method */
        foreach ($shippingMethods as $method) {
            if ($method['method']->getIsEnabled() == true && ($method['method']->getHandle() == $order->getShippingMethodHandle())) {
                /** @var ShippingMethodInterface $shippingMethod */
                $shippingMethod = $method['method'];
            }
        }

        if (null == $shippingMethod) {
            return [];
        }

        $adjustments = [];

        /** @var ShippingRule $rule */
        if ($rule = Plugin::getInstance()->getShippingMethods()->getMatchingShippingRule($order, $shippingMethod)) {

            //preparing model
            $adjustment = new OrderAdjustment;
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->orderId = $order->id;

            $affectedLineIds = [];

            //checking items tax categories
            $itemShippingTotal = 0;
            $freeShippingAmount = 0;
            foreach ($lineItems as $item) {
                $percentageRate = $rule->getPercentageRate($item->shippingCategoryId);
                $perItemRate = $rule->getPerItemRate($item->shippingCategoryId);
                $weightRate = $rule->getWeightRate($item->shippingCategoryId);

                $percentageAmount = $item->getSubtotal() * $percentageRate;
                $perItemAmount = $item->qty * $perItemRate;
                $weightAmount = ($item->weight * $item->qty) * $weightRate;
                $item->shippingCost = Currency::round($percentageAmount + $perItemAmount + $weightAmount);

                if ($item->shippingCost && !$item->purchasable->hasFreeShipping()) {
                    $affectedLineIds[] = $item->id;
                }

                $itemShippingTotal += $item->shippingCost;

                if ($item->purchasable->hasFreeShipping()) {
                    $freeShippingAmount += $item->shippingCost;
                    $item->shippingCost = 0;
                }
            }

            //amount for displaying in adjustment
            $amount = Currency::round($rule->getBaseRate()) + $itemShippingTotal - $freeShippingAmount;
            $amount = max($amount, Currency::round($rule->getMinRate()));

            if ($rule->getMaxRate()) {
                $amount = min($amount, Currency::round($rule->getMaxRate()));
            }

            $adjustment->amount = $amount;

            //real shipping base rate (can be a bit artificial because it counts min and max rate as well, but in general it equals to be $rule->baseRate)
            $order->baseShippingCost = $amount - ($itemShippingTotal - $freeShippingAmount);

            // Let the name, options and description come last since since plugins may not have all info up front.
            $adjustment->name = $shippingMethod->getName();
            $adjustment->sourceSnapshot = $rule->getOptions();
            $adjustment->sourceSnapshot = array_merge(['lineItemsAffected' => $affectedLineIds], $adjustment->optionsJson);
            $adjustment->description = $rule->getDescription();

            $adjustments[] = $adjustment;
        }

        // If the selected shippingMethod has no rules matched on this order, remove the method from the order and reset shipping costs.
        if (empty($adjustments)) {
            $order->shippingMethod = null;
            $order->baseShippingCost = 0;
            foreach ($lineItems as $item) {
                $item->shippingCost = 0;
            }
        }

        return $adjustments;
    }

}
