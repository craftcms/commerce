<?php

namespace Market\Adjusters;

use Craft\Market_AddressModel;
use Craft\Market_OrderAdjustmentModel;
use Craft\Market_OrderModel;
use Craft\Market_TaxRateModel;

/**
 * Tax Adjustments
 *
 * Class Market_TaxAdjuster
 * @package Market\Adjusters
 */
class Market_TaxAdjuster implements Market_AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Tax';

    /**
     * @param Market_OrderModel $order
     * @return Market_OrderAdjustmentModel[]
     */
    public function adjust(Market_OrderModel &$order)
    {
        $shippingAddress = $order->shippingAddress;

        if (!$shippingAddress || !$shippingAddress->id) {
            return [];
        }

        $adjustments = [];
        $taxRates = \Craft\craft()->market_taxRate->getAll(['with' => ['taxZone', 'taxZone.countries', 'taxZone.states.country']]);

        /** @var Market_TaxRateModel $rate */
        foreach ($taxRates as $rate) {
            if($adjustment = $this->getAdjustment($order, $shippingAddress, $rate)) {
                $adjustments[] = $adjustment;
                $order->adjustmentTotal += $adjustment->amount;
            }
        }

        return $adjustments;
    }

    /**
l     * @param Market_OrderModel $order
     * @param Market_AddressModel $address
     * @param Market_TaxRateModel $taxRate
     * @return false|Market_OrderAdjustmentModel
     */
    private function getAdjustment(Market_OrderModel $order, Market_AddressModel $address, Market_TaxRateModel $taxRate)
    {
        $zone = $taxRate->taxZone;

        //preparing model
        $adjustment = new Market_OrderAdjustmentModel;
        $adjustment->type = 'Tax';
        $adjustment->rate = $taxRate->rate;
        $adjustment->include = $taxRate->include;
        $adjustment->orderId = $order->id;

        //checking address
        $addressMatch = false;

        if ($zone->countryBased) {
            $countriesIds = $zone->getCountriesIds();

            if (in_array($address->countryId, $countriesIds)) {
                $adjustment->name = $address->country->name;
                $addressMatch = true;
            }
        } else {
            foreach ($zone->states as $state) {
                if ($state->country->id == $address->countryId && $state->name == $address->getStateText()) {
                    $adjustment->name = $state->formatName();
                    $addressMatch = true;
                }
            }
        }

        if(!$addressMatch) {
            return false;
        }

        //checking items tax categories
        $itemsMatch = false;
        foreach($order->lineItems as $item) {
            if($item->taxCategoryId == $taxRate->taxCategoryId) {
                $adjustment->amount += $taxRate->rate * $item->total;
                $item->subtotalIncTax += $taxRate->rate * $item->total;

                $itemsMatch = true;
            }
        }

        return $itemsMatch ? $adjustment : false;
    }
}