<?php

namespace craft\commerce\adjusters;

use craft\commerce\base\AdjusterInterface;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
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
    const ADJUSTMENT_TYPE = 'shipping';

    private $_order;

    /**
     * @param ShippingMethod $shippingMethod
     * @param ShippingRule   $rule
     *
     * @return OrderAdjustment
     */
    private function _createAdjustment($shippingMethod, $rule): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->orderId = $this->_order->id;
        $adjustment->lineItemId = null;
        $adjustment->name = $shippingMethod->getName();
        $adjustment->sourceSnapshot = $rule->getOptions();
        $adjustment->description = $rule->getDescription();

        return $adjustment;
    }

    /**
     * @param Order $order
     *
     * @return OrderAdjustment[]
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($this->_order);

        $shippingMethod = null;

        /** @var ShippingMethod $method */
        foreach ($shippingMethods as $method) {
            if ($method['method']->getIsEnabled() == true && ($method['method']->getHandle() == $this->_order->getShippingMethodHandle())) {
                /** @var ShippingMethodInterface $shippingMethod */
                $shippingMethod = $method['method'];
            }
        }

        if ($shippingMethod === null) {
            return [];
        }

        $adjustments = [];

        /** @var ShippingRule $rule */
        $rule = Plugin::getInstance()->getShippingMethods()->getMatchingShippingRule($this->_order, $shippingMethod);
        if ($rule) {
            $itemTotalAmount = 0;
            //checking items shipping categories
            foreach ($order->getLineItems() as $item) {
                if (!$item->purchasable->hasFreeShipping()) {
                    $adjustment = $this->_createAdjustment($shippingMethod, $rule);

                    $percentageRate = $rule->getPercentageRate($item->shippingCategoryId);
                    $perItemRate = $rule->getPerItemRate($item->shippingCategoryId);
                    $weightRate = $rule->getWeightRate($item->shippingCategoryId);

                    $percentageAmount = $item->getSubtotal() * $percentageRate;
                    $perItemAmount = $item->qty * $perItemRate;
                    $weightAmount = ($item->weight * $item->qty) * $weightRate;

                    $adjustment->amount = Currency::round($percentageAmount + $perItemAmount + $weightAmount);
                    $adjustment->lineItemId = $item->id;
                    $adjustments[] = $adjustment;
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

        // If the selected shippingMethod has no rules matched on this order, remove the method from the order and reset shipping costs.
        if (empty($adjustments)) {
            $this->_order->shippingMethod = null;
        }

        return $adjustments;
    }
}
