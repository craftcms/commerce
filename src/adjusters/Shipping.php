<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\adjusters;

use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;

/**
 * Tax Adjustments
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Shipping extends Component implements AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'shipping';


    /**
     * @var
     */
    private $_order;

    /**
     * @var bool
     */
    private $_isEstimated = false;

    /**
     * @var bool
     */
    private $_consolidateShippingToSingleAdjustment = false;

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;
        $this->_isEstimated = (!$order->shippingAddressId && $order->estimatedShippingAddressId);

        $shippingMethod = $order->getShippingMethod();
        $lineItems = $order->getLineItems();

        if ($shippingMethod === null) {
            return [];
        }

        $nonShippableItems = [];

        foreach ($lineItems as $item) {
            $purchasable = $item->getPurchasable();
            if ($purchasable && !$purchasable->getIsShippable()) {
                $nonShippableItems[$item->id] = $item->id;
            }
        }

        // Are all line items non shippable items? No shipping cost.
        if (count($lineItems) == count($nonShippableItems)) {
            return [];
        }

        $adjustments = [];

        $discounts = Plugin::getInstance()->getDiscounts()->getAllActiveDiscounts($order);

        /** @var ShippingRule $rule */
        $rule = $shippingMethod->getMatchingShippingRule($this->_order);
        if ($rule) {
            $itemTotalAmount = 0;

            // Check for order level discounts for shipping
            $hasDiscountRemoveShippingCosts = false;
            foreach ($discounts as $discount) {
                if ($discount->hasFreeShippingForOrder && Plugin::getInstance()->getDiscounts()->matchOrder($this->_order, $discount)) {
                    $hasDiscountRemoveShippingCosts = true;
                }
            }

            if (!$hasDiscountRemoveShippingCosts) {
                //checking items shipping categories
                foreach ($order->getLineItems() as $item) {
                    // Lets match the discount now for free shipped items and not even make a shipping cost for the line item.
                    $hasFreeShippingFromDiscount = false;
                    foreach ($discounts as $discount) {
                        if ($discount->hasFreeShippingForMatchingItems && Plugin::getInstance()->getDiscounts()->matchLineItem($item, $discount, true)) {
                            $hasFreeShippingFromDiscount = true;
                        }
                    }

                    $freeShippingFlagOnProduct = $item->purchasable->hasFreeShipping();
                    $shippable = $item->purchasable->getIsShippable();
                    if (!$freeShippingFlagOnProduct && !$hasFreeShippingFromDiscount && $shippable) {
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

        if($this->_consolidateShippingToSingleAdjustment)
        {
            $amount = 0;
            foreach ($adjustments as $adjustment){
                $amount += $adjustment->amount;
            }

            //preparing model
            $adjustment = new OrderAdjustment;
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->setOrder($this->_order);
            $adjustment->name = $shippingMethod->getName();
            $adjustment->amount = $amount;
            $adjustment->description = $rule->getDescription();
            $adjustment->isEstimated = $this->_isEstimated;
            $adjustment->sourceSnapshot = [];

            return [$adjustment];
        }

        return $adjustments;
    }


    /**
     * @param ShippingMethod $shippingMethod
     * @param ShippingRule $rule
     * @return OrderAdjustment
     */
    private function _createAdjustment($shippingMethod, $rule): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->setOrder($this->_order);
        $adjustment->name = $shippingMethod->getName();
        $adjustment->description = $rule->getDescription();
        $adjustment->isEstimated = $this->_isEstimated;
        $adjustment->sourceSnapshot = $rule->toArray();

        return $adjustment;
    }
}
