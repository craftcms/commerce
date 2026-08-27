<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster;

use craft\commerce\adjusters\Discount as LegacyDiscount;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Promotion\Data\Discount as DiscountModel;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsEvent;
use CraftCms\Commerce\Promotion\Models\Discount as DiscountRecord;
use Money\Teller;
use yii\base\Event;

/**
 * Discount Adjuster
 */
class Discount implements AdjusterInterface
{
    /**
     * The discount adjustment type.
     */
    public const ADJUSTMENT_TYPE = 'discount';

    /**
     * @event DiscountAdjustmentsEvent The event that is triggered after a discount has matched the order and before it returns its adjustments.
     *
     * Fired on the legacy `craft\commerce\adjusters\Discount` class name for backward compatibility
     * with existing `Event::on(craft\commerce\adjusters\Discount::class, ...)` listeners, since
     * `yii\base\Event::trigger()`/`hasHandlers()` match by class-name string and don't require an
     * instance of that class.
     *
     * TODO: migrate event firing to Laravel once event system is bridged
     */
    public const EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED = 'afterDiscountAdjustmentsCreated';

    private Order $_order;

    private float $_discountTotal = 0;

    /**
     * Temporary feature flag for testing
     */
    private bool $_spreadBaseOrderDiscountsToLineItems = true;

    private array $_discountUnitPricesByLineItem = [];

    #[\Override]
    public function adjust(Order $order): array
    {
        $this->_order = $order;
        $teller = $this->_getTeller();

        $adjustments = [];
        $availableDiscounts = [];
        $discounts = app(Discounts::class)->getAllActiveDiscounts($order);

        foreach ($discounts as $discount) {
            if (app(Discounts::class)->matchOrder($order, $discount)) {
                $availableDiscounts[] = $discount;
            }
        }

        if (!$availableDiscounts) {
            return [];
        }

        foreach ($this->_order->getLineItems() as $lineItem) {
            $lineItemHashId = spl_object_hash($lineItem);
            $lineItemDiscountAmount = $lineItem->getDiscount();
            if ($lineItemDiscountAmount) {
                $discountedUnitPrice = (float)$teller->add(
                    $lineItem->salePrice,
                    $teller->divide($lineItemDiscountAmount, $lineItem->qty)
                );
                $this->_discountUnitPricesByLineItem[$lineItemHashId] = $discountedUnitPrice;
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
                $priceByLineItem[$lineItemHashId] = (float)$teller->add($lineItem->getSubtotal(), $lineItem->getDiscount());
            }

            $orderLevelAdjustments = [];

            // Remove other plugins previous order level discount adjustments
            $allAdjustments = $this->_order->getAdjustments();
            foreach ($allAdjustments as $key => $previousAdjustment) {
                if ($previousAdjustment->type == self::ADJUSTMENT_TYPE && !$previousAdjustment->getLineItem()) {
                    $orderLevelAdjustments[] = $previousAdjustment;
                    unset($allAdjustments[$key]);
                }
            }
            $this->_order->setAdjustments($allAdjustments);

            // Our adjustments
            foreach ($adjustments as $key => $adjustment) {
                if ($adjustment->getLineItem()) {
                    $lineItemHashId = spl_object_hash($adjustment->getLineItem());
                    // Reduce the price of the line item by the amount of discount from the adjuster
                    $priceByLineItem[$lineItemHashId] = (float)$teller->add($priceByLineItem[$lineItemHashId] ?? 0, $adjustment->amount);
                } else {
                    // If it's an order level adjustment lets track it, but remove it from the standard adjustments.
                    $orderLevelAdjustments[] = $adjustment;
                    unset($adjustments[$key]);
                }
            }

            $lineItemsByPrice = $this->_order->getLineItems();
            usort($lineItemsByPrice, static function(LineItem $a, LineItem $b) use ($priceByLineItem) {
                return $priceByLineItem[spl_object_hash($b)] <=> $priceByLineItem[spl_object_hash($a)];
            });

            // Remove non-promotable line items
            $lineItemsByPrice = Arr::where($lineItemsByPrice, fn(LineItem $lineItem) => $lineItem->getIsPromotable());

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
                        if ($currentDiscountAmountRemaining >= $priceByLineItem[$lineItemHashId]) {
                            $amount = (float)$teller->multiply($priceByLineItem[$lineItemHashId], -1); // Take the full price of the item off
                            $priceByLineItem[$lineItemHashId] = 0; // Price is now free
                            $currentDiscountAmountRemaining = (float)$teller->add($currentDiscountAmountRemaining, $amount); // Reduce the price of the discount remaining so it can still be used
                        } else {
                            // Is the current amount of discount remaining less than the current price of the item? Take the whole discount remainder off the item.
                            if ($currentDiscountAmountRemaining < $priceByLineItem[$lineItemHashId]) {
                                $amount = (float)$teller->multiply($currentDiscountAmountRemaining, -1); // The adjustment amount is always a negative number
                                $currentDiscountAmountRemaining = 0; // Reduce the amount of discount to zero since there is none left
                                $priceByLineItem[$lineItemHashId] = (float)$teller->add($priceByLineItem[$lineItemHashId], $amount); // Reduce the price of the item that we are tracking
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
     * @return OrderAdjustment[]|false
     */
    private function _getAdjustments(DiscountModel $discount): array|false
    {
        $adjustments = [];
        $teller = $this->_getTeller();

        $matchingLineIds = [];
        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            // Order is already a match to this discount, or we wouldn't get here.
            if (app(Discounts::class)->matchLineItem($item, $discount, false)) {
                $matchingLineIds[] = $lineItemHashId;
            }
        }

        foreach ($this->_order->getLineItems() as $item) {
            $lineItemHashId = spl_object_hash($item);
            if ($matchingLineIds && in_array($lineItemHashId, $matchingLineIds, false)) {
                $adjustment = $this->_createOrderAdjustment($discount);
                $adjustment->setLineItem($item);
                $discountAmountPerItemPreDiscounts = 0;
                $amountPerItem = Currency::round($discount->perItemDiscount);

                if ($discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
                    $discountAmountPerItemPreDiscounts = (float)$teller->multiply($item->salePrice, $discount->percentDiscount);
                }

                $unitPrice = $this->_discountUnitPricesByLineItem[$lineItemHashId] ?? $item->salePrice;

                $lineItemSubtotal = (float)$teller->multiply($item->qty, $unitPrice);

                $unitPrice = max((float)$teller->add($unitPrice, $amountPerItem), 0);

                if ($unitPrice > 0) {
                    if ($discount->percentageOffSubject == DiscountRecord::TYPE_ORIGINAL_SALEPRICE) {
                        $discountedUnitPrice = (float)$teller->add($unitPrice, $discountAmountPerItemPreDiscounts);
                    } else {
                        $discountedUnitPrice = (float)$teller->add(
                            $unitPrice,
                            $teller->multiply($unitPrice, $discount->percentDiscount)
                        );
                    }

                    $discountedSubtotal = (float)$teller->multiply($discountedUnitPrice, $item->qty);
                    $amountOfPercentDiscount = (float)$teller->subtract($discountedSubtotal, $lineItemSubtotal);
                    $this->_discountUnitPricesByLineItem[$lineItemHashId] = $discountedUnitPrice;
                    $adjustment->amount = $amountOfPercentDiscount; //Adding already rounded
                } else {
                    $adjustment->amount = -$lineItemSubtotal;
                    $this->_discountUnitPricesByLineItem[$lineItemHashId] = 0;
                }

                if ($adjustment->amount != 0) {
                    $this->_discountTotal = (float)$teller->add($this->_discountTotal, $adjustment->amount);
                    $adjustments[] = $adjustment;
                }
            }
        }

        if ($discount->baseDiscount != 0) {
            $baseDiscountAdjustment = $this->_createOrderAdjustment($discount);
            $baseDiscountAdjustment->amount = $discount->baseDiscount;
            $adjustments[] = $baseDiscountAdjustment;
        }

        // only display adjustment if an amount was calculated
        if (!count($adjustments)) {
            return false;
        }

        // Raise the 'afterDiscountAdjustmentsCreated' event
        $event = new DiscountAdjustmentsEvent(
            order: $this->_order,
            discount: $discount,
            adjustments: $adjustments,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        if (Event::hasHandlers(LegacyDiscount::class, self::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED)) {
            /** @phpstan-ignore-next-line argument.type (TODO: migrate event firing to Laravel once event system is bridged) */
            Event::trigger(LegacyDiscount::class, self::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, $event);
        }

        if (!$event->isValid) {
            return false;
        }

        return $event->adjustments;
    }

    /**
     * @throws \RuntimeException
     */
    private function _getTeller(): Teller
    {
        return app(Currencies::class)->getTeller($this->_order->currency);
    }
}
