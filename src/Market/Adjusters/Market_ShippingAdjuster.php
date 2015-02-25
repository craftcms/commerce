<?php

namespace Market\Adjusters;

use Craft\Market_AddressModel;
use Craft\Market_LineItemModel;
use Craft\Market_OrderAdjustmentModel;
use Craft\Market_OrderModel;
use Craft\Market_ShippingMethodModel;
use Craft\Market_TaxRateModel;

/**
 * Tax Adjustments
 *
 * Class Market_ShippingAdjuster
 * @package Market\Adjusters
 */
class Market_ShippingAdjuster implements Market_AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Shipping';

    /**
     * @param Market_OrderModel $order
     * @param Market_LineItemModel[] $lineItems
     * @return \Craft\Market_OrderAdjustmentModel[]
     */
    public function adjust(Market_OrderModel &$order, array $lineItems = [])
    {
        $shippingMethod = \Craft\craft()->market_shippingMethod->getById($order->shippingMethodId);

        if (!$shippingMethod->id) {
            return [];
        }

        $adjustments = [];

        if($rule = \Craft\craft()->market_shippingMethod->getMatchingRule($order, $shippingMethod)) {
            //preparing model
            $adjustment = new Market_OrderAdjustmentModel;
            $adjustment->type = self::ADJUSTMENT_TYPE;
            $adjustment->name = $shippingMethod->name;
            $adjustment->rate = 0;
            $adjustment->orderId = $order->id;

            //checking items tax categories
            $weight = $qty = $price = 0;
            foreach($lineItems as $item) {
                $weight += $item->qty * $item->variant->weight;
                $qty += $item->qty;
                $price += $item->subtotal;
            }

            $adjustment->amount = $rule->calculate($weight, $qty, $price);
            $adjustments[] = $adjustment;
        }

        return $adjustments;
    }
}