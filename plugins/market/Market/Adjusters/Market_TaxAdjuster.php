<?php

namespace Market\Adjusters;

use Craft\Market_AddressModel;
use Craft\Market_LineItemModel;
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
     * @param Market_LineItemModel[] $lineItems
     * @return \Craft\Market_OrderAdjustmentModel[]
     */
    public function adjust(Market_OrderModel &$order, array $lineItems = [])
    {
        $shippingAddress = \Craft\craft()->market_address->getById($order->shippingAddressId);

        if (!$shippingAddress->id) {
            return [];
        }

        $adjustments = [];
        $taxRates = \Craft\craft()->market_taxRate->getAll(['with' => ['taxZone', 'taxZone.countries', 'taxZone.states.country']]);

        /** @var Market_TaxRateModel $rate */
        foreach ($taxRates as $rate) {
            if($adjustment = $this->getAdjustment($order, $lineItems, $shippingAddress, $rate)) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @param Market_OrderModel $order
     * @param Market_LineItemModel[] $lineItems
     * @param Market_AddressModel $address
     * @param Market_TaxRateModel $taxRate
     * @return Market_OrderAdjustmentModel|false
     */
    private function getAdjustment(Market_OrderModel $order, array $lineItems, Market_AddressModel $address, Market_TaxRateModel $taxRate)
    {
        $zone = $taxRate->taxZone;

        //preparing model
        $adjustment = new Market_OrderAdjustmentModel;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $taxRate->name;
        $adjustment->description = $taxRate->rate * 100 . '%' . ($taxRate->include ? ' inc' : '');
        $adjustment->orderId = $order->id;
        $adjustment->optionsJson = $taxRate->attributes;

        //checking address
        $addressMatch = false;

        if ($zone->countryBased) {
            $countriesIds = $zone->getCountriesIds();

            if (in_array($address->countryId, $countriesIds)) {
                $addressMatch = true;
            }
        } else {
            foreach ($zone->states as $state) {
                if ($state->country->id == $address->countryId && $state->name == $address->getStateText()) {
                    $addressMatch = true;
                    break;
                }
            }
        }

        if(!$addressMatch) {
            if($taxRate->include) {
                //excluding taxes included in price
                foreach($lineItems as $item) {
                    if($item->taxCategoryId == $taxRate->taxCategoryId) {
                        $item->taxAmount += - $taxRate->rate * $item->getPriceWithoutShipping();
                    }
                }
            }

            return false;
        }

        //checking items tax categories
        $itemsMatch = false;
        foreach($lineItems as $item) {
            if($item->taxCategoryId == $taxRate->taxCategoryId) {
                $itemTaxAmount = $taxRate->rate * $item->getPriceWithoutShipping();
                $adjustment->amount += $itemTaxAmount;

                if(!$taxRate->include) {
                    $item->taxAmount += $itemTaxAmount;
                }

                $itemsMatch = true;
            }
        }

        return $itemsMatch ? $adjustment : false;
    }
}