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
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\helpers\ArrayHelper;
use DateTime;

/**
 * Discount Adjuster
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Discount extends Component implements AdjusterInterface
{
    /**
     * The discount adjustment type.
     */
    const ADJUSTMENT_TYPE = 'discount';

    /**
     * @event DiscountAdjustmentsEvent The event that is triggered after a discount has matched the order and before it returns its adjustments.
     *
     * ```php
     * use craft\commerce\adjusters\Discount;
     * use craft\commerce\elements\Order;
     * use craft\commerce\models\Discount as DiscountModel;
     * use craft\commerce\models\OrderAdjustment;
     * use craft\commerce\events\DiscountAdjustmentsEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Discount::class,
     *     Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED,
     *     function(DiscountAdjustmentsEvent $event) {
     *         // @var Order $order
     *         $order = $event->order;
     *         // @var DiscountModel $discount
     *         $discount = $event->discount;
     *         // @var OrderAdjustment[] $adjustments
     *         $adjustments = $event->adjustments;
     * 
     *         // Use a third party to check order data and modify the adjustments
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED = 'afterDiscountAdjustmentsCreated';


    /**
     * @var Order
     */
    private $_order;

    /**
     * @var
     */
    private $_discount;

    /**
     * @var array
     */
    private $_appliedDiscounts = [];

    /*
     * @var
     */
    private $_discountTotal = 0;

    /**
     * Temporary feature flag for testing
     *
     * @var bool
     */
    private $_spreadBaseOrderDiscountsToLineItems = true;

    /**
     * @var array
     */
    private $_discountUnitPricesByLineItem = [];

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

        $adjustments = [];
        $availableDiscounts = [];
        $discounts = Plugin::getInstance()->getDiscounts()->getAllActiveDiscounts($order);

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

        if ($this->_spreadBaseOrderDiscountsToLineItems) {

            $priceByLineItem = [];
            foreach ($this->_order->getLineItems() as $lineItem) {
                $lineItemHashId = spl_object_hash($lineItem);
                $priceByLineItem[$lineItemHashId] = $lineItem->getSubtotal();
            }

            $orderLevelAdjustments = [];

            foreach ($adjustments as $key => $adjustment) {
                if ($adjustment->getLineItem()) {
                    $lineItemHashId = spl_object_hash($adjustment->getLineItem());
                    // Reduce the price of the line item by the amount of discount from the adjuster
                    $priceByLineItem[$lineItemHashId] += $adjustment->amount;
                } else {
                    // If it's an order level adjustment lets track it, but remove it from the standard adjustments.
                    $orderLevelAdjustments[] = $adjustment;
                    unset($adjustments[$key]);
                }
            }

            $lineItemsByPrice = $this->_order->getLineItems();
            ArrayHelper::multisort($lineItemsByPrice, static function($item) use ($priceByLineItem) {
                // sort by age if it exists or by name otherwise
                /** @var LineItem $item */
                $lineItemHashId = spl_object_hash($item);
                return $priceByLineItem[$lineItemHashId];
            }, SORT_DESC);


            // Loop over each order level adjustment and add an adjustment to each line item until it runs out.
            foreach ($orderLevelAdjustments as $orderLevelAdjustment) {
                // Track the amount of discount (as a positive number), as we are going to deduct it as we use it up on line items.
                $currentDiscountAmountRemaining = -$orderLevelAdjustment->amount;

                // Lets loop over the line items and apply some or all of the discount amount
                foreach ($lineItemsByPrice as $lineItem) {

                    // We need to know the hash ID of the line item since some line items do not have an ID yet
                    $lineItemHashId = spl_object_hash($lineItem);

                    // Do we have any discount left to use, and can the line item still be discounted?
                    if ($currentDiscountAmountRemaining > 0 && $priceByLineItem[$lineItemHashId] > 0) {

                        // The amount of the adjustment for this line item.
                        $amount = 0;

                        // Is the amount of discount greater than the price of the item
                        if ($currentDiscountAmountRemaining  >= $priceByLineItem[$lineItemHashId]) {
                            $amount = $priceByLineItem[$lineItemHashId] * -1; // Take the full price of the item off
                            $priceByLineItem[$lineItemHashId] = 0; // Price is now free
                            $currentDiscountAmountRemaining += $amount; // Reduce the price of the discount remaining so it can still be used
                        } else {
                            // Is the current amount of discount remaining less than the current price of the item? Take the whole discount remainder off the item.
                            if ($currentDiscountAmountRemaining < $priceByLineItem[$lineItemHashId]) {
                                $amount = $currentDiscountAmountRemaining * -1; // The adjustment amount is always a negative number
                                $currentDiscountAmountRemaining = 0; // Reduce the amount of discount to zero since there is none left
                                $priceByLineItem[$lineItemHashId] += $amount; // Reduce the price of the item that we are tracking
                            }
                        }

                        if ($amount) {
                            /** @var OrderAdjustment $adjustment */
                            $adjustment = clone $orderLevelAdjustment;
                            $adjustment->amount = $amount;
                            $adjustment->setLineItem($lineItem);
                            $adjustments[] = $adjustment;
                        }
                    }
                }
            }
        }


        return $adjustments;
    }


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
        $snapshot = $discount->toArray();
        $snapshot['discountUseId'] = $discount->id ?? null;
        $adjustment->sourceSnapshot = $snapshot;

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

        $matchingLineIds = [];
        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            // Order is already a match to this discount, or we wouldn't get here.
            if (Plugin::getInstance()->getDiscounts()->matchLineItem($item, $this->_discount, false)) {
                $matchingLineIds[] = $lineItemHashId;
            }
        }

        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            if ($matchingLineIds && in_array($lineItemHashId, $matchingLineIds, false)) {
                $adjustment = $this->_createOrderAdjustment($this->_discount);
                $adjustment->setLineItem($item);
                $discountAmountPerItemPreDiscounts = 0;
                $amountPerItem = Currency::round($this->_discount->perItemDiscount);

                if ($this->_discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
                    $discountAmountPerItemPreDiscounts = ($this->_discount->percentDiscount * $item->salePrice);
                }

                $unitPrice = $this->_discountUnitPricesByLineItem[$lineItemHashId] ?? $item->salePrice;

                $lineItemSubtotal = Currency::round($unitPrice * $item->qty);

                $unitPrice = max($unitPrice + $amountPerItem, 0);

                if ($unitPrice > 0) {
                    if ($this->_discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
                        $discountedUnitPrice = $unitPrice + $discountAmountPerItemPreDiscounts;
                    } else {
                        $discountedUnitPrice = $unitPrice + ($this->_discount->percentDiscount * $unitPrice);
                    }

                    $discountedSubtotal = Currency::round($discountedUnitPrice * $item->qty);
                    $amountOfPercentDiscount = $discountedSubtotal - $lineItemSubtotal;
                    $this->_discountUnitPricesByLineItem[$lineItemHashId] = $discountedUnitPrice;
                    $adjustment->amount = $amountOfPercentDiscount; //Adding already rounded
                } else {
                    $adjustment->amount = -$lineItemSubtotal;
                    $this->_discountUnitPricesByLineItem[$lineItemHashId] = 0;
                }

                if ($adjustment->amount != 0) {
                    $this->_discountTotal += $adjustment->amount;
                    $adjustments[] = $adjustment;
                }
            }
        }

        if ($discount->baseDiscount !== null && $discount->baseDiscount != 0) {
            $baseDiscountAdjustment = $this->_createOrderAdjustment($discount);
            $baseDiscountAdjustment->amount = $this->_getBaseDiscountAmount($discount);
            $adjustments[] = $baseDiscountAdjustment;
        }

        // only display adjustment if an amount was calculated
        if (!count($adjustments)) {
            return false;
        }

        // Raise the 'afterDiscountAdjustmentsCreated' event
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

    /**
     * @param DiscountModel $discount
     * @return float|int
     */
    private function _getBaseDiscountAmount(DiscountModel $discount)
    {
        if ($discount->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_VALUE) {
            return $discount->baseDiscount;
        }

        $total = $this->_order->getItemSubtotal();

        if ($discount->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED || $discount->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED) {
            $total += $this->_discountTotal;
        }

        if ($discount->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED || $discount->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL) {
            $total += $this->_order->getTotalShippingCost();
        }

        return ($total / 100) * $discount->baseDiscount;
    }
}
