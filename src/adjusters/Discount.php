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
use craft\commerce\events\DiscountAdjustmentsEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Discount as DiscountModel;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;

/**
 * Discount Adjuster
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Discount extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    /**
     * The discount adjustment type.
     */
    const ADJUSTMENT_TYPE = 'discount';

    /**
     * @event DiscountAdjustmentsEvent The event that is raised after a discount has matched the order and before it returns it's adjustments.
     *
     * Plugins can get notified before a line item is being saved
     *
     * ```php
     * use craft\commerce\adjusters\Discount;
     * use yii\base\Event;
     *
     * Event::on(Discount::class, Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, function(DiscountAdjustmentsEvent $e) {
     *     // Do something - perhaps use a 3rd party to check order data and cancel all adjustments for this discount or modify the adjustments.
     * });
     * ```
     */
    const EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED = 'afterDiscountAdjustmentsCreated';


    // Properties
    // =========================================================================

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var bool Whole order has free shipping applied
     */
    private $_hasFreeShippingForOrderApplied;

    /**
     * @var
     */
    private $_discount;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

        $adjustments = [];
        $availableDiscounts = [];
        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();

        foreach ($discounts as $discount) {
            if (Plugin::getInstance()->getDiscounts()->matchOrder($order, $discount)) {
                $availableDiscounts[] = $discount;
            }
        }

        foreach ($availableDiscounts as $discount) {
            $newAdjustments = $this->_getAdjustments($discount);
            if ($newAdjustments) {
                array_push($adjustments, ...$newAdjustments);

                if ($discount->stopProcessing) {
                    break;
                }
            }
        }

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param DiscountModel $discount
     * @return OrderAdjustment
     */
    private function _createOrderAdjustment(DiscountModel $discount): OrderAdjustment
    {
        //preparing model
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $discount->name;
        $adjustment->setOrder($this->_order);
        $adjustment->description = $discount->description;
        $adjustment->sourceSnapshot = $discount->toArray();

        return $adjustment;
    }

    /**
     * @param DiscountModel $discount
     * @return OrderAdjustment[]|false
     */
    private function _getAdjustments(DiscountModel $discount)
    {
        $adjustments = [];

        $this->_discount = $discount;

        $now = new \DateTime();
        $from = $this->_discount->dateFrom;
        $to = $this->_discount->dateTo;
        if (($from && $from > $now) || ($to && $to < $now)) {
            return false;
        }

        //checking items
        $matchingQty = 0;
        $matchingTotal = 0;
        $matchingLineIds = [];
        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            if (Plugin::getInstance()->getDiscounts()->matchLineItem($item, $this->_discount)) {
                if (!$this->_discount->allGroups) {
                    $customer = $this->_order->getCustomer();
                    $user = $customer ? $customer->getUser() : null;
                    $userGroups = Plugin::getInstance()->getCustomers()->getUserGroupIdsForUser($user);
                    if ($user && array_intersect($userGroups, $this->_discount->getUserGroupIds())) {
                        $matchingLineIds[] = $lineItemHashId;
                        $matchingQty += $item->qty;
                        $matchingTotal += $item->getSubtotal();
                    }
                } else {
                    $matchingLineIds[] = $lineItemHashId;
                    $matchingQty += $item->qty;
                    $matchingTotal += $item->getSubtotal();
                }
            }
        }

        if (!$matchingQty) {
            return false;
        }

        // Have they entered a max qty?
        if ($this->_discount->maxPurchaseQty > 0 && $matchingQty > $this->_discount->maxPurchaseQty) {
            return false;
        }

        // Reject if they have not added enough matching items
        if ($matchingQty < $this->_discount->purchaseQty) {
            return false;
        }

        // Reject if the matching items values is not enough
        if ($matchingTotal < $this->_discount->purchaseTotal) {
            return false;
        }

        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            if ($matchingLineIds && in_array($lineItemHashId, $matchingLineIds, false)) {
                $adjustment = $this->_createOrderAdjustment($this->_discount);
                $adjustment->setLineItem($item);

                $amountPerItem = Currency::round($this->_discount->perItemDiscount * $item->qty);

                //Default is percentage off already discounted price
                $existingLineItemDiscount = $item->getAdjustmentsTotalByType('discount');
                $existingLineItemPrice = ($item->getSubtotal() + $existingLineItemDiscount);
                $amountPercentage = Currency::round($this->_discount->percentDiscount * $existingLineItemPrice);

                if ($this->_discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
                    $amountPercentage = Currency::round($this->_discount->percentDiscount * $item->getSubtotal());
                }

                $adjustment->amount = $amountPerItem + $amountPercentage;

                if ($adjustment->amount != 0) {
                    $adjustments[] = $adjustment;
                }
            }
        }

        if ($discount->hasFreeShippingForOrder && !$this->_hasFreeShippingForOrderApplied) {
            // Don't remove order shipping cost more than once
            $this->_hasFreeShippingForOrderApplied = true;
            $adjustment = $this->_createOrderAdjustment($this->_discount);
            $adjustment->amount = $this->_order->getAdjustmentsTotalByType('shipping') * -1;
            if ($this->_order->getAdjustmentsTotalByType('shipping') > 0) {
                $adjustments[] = $adjustment;
            }
        }

        if ($discount->baseDiscount !== null && $discount->baseDiscount != 0) {
            $baseDiscountAdjustment = $this->_createOrderAdjustment($discount);
            $baseDiscountAdjustment->amount = $discount->baseDiscount;
            $adjustments[] = $baseDiscountAdjustment;
        }

        // only display adjustment if an amount was calculated
        if (!count($adjustments)) {
            return false;
        }

        // Raise the 'beforeMatchLineItem' event
        $event = new DiscountAdjustmentsEvent([
            'order' => $this->_order,
            'discount' => $discount,
            'adjustments' => $adjustments
        ]);

        $this->trigger(self::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, $event);

        if (!$event->isValid) {
            return false;
        }

        return $event->adjustments;
    }
}
