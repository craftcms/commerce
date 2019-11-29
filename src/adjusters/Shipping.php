<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\adjusters;

use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Discount;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\Category;

/**
 * Tax Adjustments
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Shipping extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    const ADJUSTMENT_TYPE = 'shipping';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_order;

    /**
     * @var bool
     */
    private $_isEstimated = false;

    // Public Methods
    // =========================================================================

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
            if($purchasable && !$purchasable->getIsShippable())
            {
                $nonShippableItems[$item->id] = $item->id;
            }
        }

        // Are all line items non shippable items? No shipping cost.
        if(count($lineItems) == count($nonShippableItems))
        {
            return [];
        }

        $adjustments = [];

        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();

        /** @var ShippingRule $rule */
        $rule = $shippingMethod->getMatchingShippingRule($this->_order);
        if ($rule) {
            $itemTotalAmount = 0;

            // Check for order level discounts for shipping
            $hasDiscountRemoveShippingCosts = false;
            foreach ($discounts as $discount) {
                if ($discount->hasFreeShippingForOrder && $this->_matchOrderAndLineItems($this->_order, $discount)) {
                    $hasDiscountRemoveShippingCosts = true;
                }
            }

            if (!$hasDiscountRemoveShippingCosts) {
                //checking items shipping categories
                foreach ($order->getLineItems() as $item) {

                    $freeShippingFlagOnProduct = $item->purchasable->hasFreeShipping();
                    $shippable =  $item->purchasable->getIsShippable();
                    if (!$freeShippingFlagOnProduct && $shippable) {
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

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

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

    /**
     * @param Order $order
     * @param Discount $discount
     * @return bool
     * @throws \yii\base\InvalidConfigException
     * @deprecated in 2.2.9. Matching order and lineItems for discounts will be refactored in 3.0
     */
    private function _matchOrderAndLineItems(Order $order, Discount $discount): bool
    {
        if (!$discount->enabled) {
            return false;
        }

        if ($discount->code && $discount->code != $order->couponCode) {
            return false;
        }

        $customer = $order->getCustomer();
        $user = $customer ? $customer->getUser() : null;

        $now = $order->dateUpdated ?? new DateTime();
        $from = $discount->dateFrom;
        $to = $discount->dateTo;
        if (($from && $from > $now) || ($to && $to < $now)) {
            return false;
        }

        if (!$discount->allGroups) {
            $groupIds = $user ? Plugin::getInstance()->getCustomers()->getUserGroupIdsForUser($user) : [];
            if (empty(array_intersect($groupIds, $discount->getUserGroupIds()))) {
                return false;
            }
        }

        // Coupon based checks
        if ($discount->code && $order->couponCode && (strcasecmp($order->couponCode, $discount->code) == 0)) {
            if ($discount->totalUseLimit > 0 && $discount->totalUses >= $discount->totalUseLimit) {
                return false;
            }

            if ($discount->perUserLimit > 0 && !$user) {
                return false;
            }

            if ($discount->perUserLimit > 0 && $user) {
                // The 'Per User Limit' can only be tracked against logged in users since guest customers are re-generated often
                $usage = (new Query())
                    ->select(['uses'])
                    ->from([Table::CUSTOMER_DISCOUNTUSES])
                    ->where(['customerId' => $customer->id, 'discountId' => $discount->id])
                    ->scalar();

                if ($usage && $usage >= $discount->perUserLimit) {
                    return false;
                }
            }

            if ($discount->perEmailLimit > 0 && $order->getEmail()) {
                $usage = (new Query())
                    ->select(['uses'])
                    ->from([Table::EMAIL_DISCOUNTUSES])
                    ->where(['email' => $order->getEmail(), 'discountId' => $discount->id])
                    ->scalar();

                if ($usage && $usage >= $discount->perEmailLimit) {
                    return false;
                }
            }
        }

        // Check to see if we need to match on data related to the lineItems
        if (($discount->getPurchasableIds() && !$discount->allPurchasables) || ($discount->getCategoryIds() && !$discount->allCategories)) {
            $lineItemMatch = false;
            foreach ($order->getLineItems() as $lineItem) {
                if ($lineItem->onSale && $discount->excludeOnSale) {
                    continue;
                }

                // can't match something not promotable
                if (!$lineItem->purchasable->getIsPromotable()) {
                    continue;
                }

                if ($discount->getPurchasableIds() && !$discount->allPurchasables) {
                    $purchasableId = $lineItem->purchasableId;
                    if (!in_array($purchasableId, $discount->getPurchasableIds(), true)) {
                        continue;
                    }
                }

                if ($discount->getCategoryIds() && !$discount->allCategories && $lineItem->getPurchasable()) {
                    $purchasable = $lineItem->getPurchasable();

                    if (!$purchasable) {
                        continue;
                    }

                    $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
                    $relatedCategories = Category::find()->relatedTo($relatedTo)->ids();
                    $purchasableIsRelateToOneOrMoreCategories = (bool)array_intersect($relatedCategories, $discount->getCategoryIds());
                    if (!$purchasableIsRelateToOneOrMoreCategories) {
                        continue;
                    }
                }

                $lineItemMatch = true;
                break;
            }

            return $lineItemMatch;
        }

        return true;
    }
}
