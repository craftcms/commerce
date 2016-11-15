<?php

namespace Commerce\Adjusters;

use Commerce\Helpers\CommerceCurrencyHelper;
use Craft\Commerce_AddressModel;
use Craft\Commerce_LineItemModel;
use Craft\Commerce_OrderAdjustmentModel;
use Craft\Commerce_OrderModel;
use Craft\Commerce_TaxRateModel;
use Craft\Commerce_TaxZoneModel;
use Snowcap\Vat\Validation;

/**
 * Tax Adjustments
 *
 * Class Commerce_TaxAdjuster
 *
 * @package Commerce\Adjusters
 */
class Commerce_TaxAdjuster implements Commerce_AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Tax';

    /**
     * @param Commerce_OrderModel $order
     * @param Commerce_LineItemModel[] $lineItems
     *
     * @return \Craft\Commerce_OrderAdjustmentModel[]
     */
    public function adjust(Commerce_OrderModel &$order, array $lineItems = [])
    {
        $address = \Craft\craft()->commerce_addresses->getAddressById($order->shippingAddressId);

        if (\Craft\craft()->config->get('useBillingAddressForTax','commerce'))
        {
            $address = \Craft\craft()->commerce_addresses->getAddressById($order->billingAddressId);
        }

        $adjustments = [];
        $taxRates = \Craft\craft()->commerce_taxRates->getAllTaxRates([
            'with' => ['taxZone', 'taxZone.countries', 'taxZone.states.country'],
        ]);

        /** @var Commerce_TaxRateModel $rate */
        foreach ($taxRates as $rate) {
            if ($adjustment = $this->getAdjustment($order, $lineItems, $address, $rate)) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @param Commerce_OrderModel $order
     * @param Commerce_LineItemModel[] $lineItems
     * @param Commerce_AddressModel $address
     * @param Commerce_TaxRateModel $taxRate
     *
     * @return Commerce_OrderAdjustmentModel|false
     */
    private function getAdjustment(Commerce_OrderModel $order, array $lineItems, Commerce_AddressModel $address = null, Commerce_TaxRateModel $taxRate)
    {
        $zone = $taxRate->taxZone;

        //preparing model
        $adjustment = new Commerce_OrderAdjustmentModel;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $taxRate->name;
        $adjustment->description = $taxRate->rate * 100 . '%' . ($taxRate->include ? ' inc' : '');
        $adjustment->orderId = $order->id;
        $adjustment->optionsJson = $taxRate->attributes;

        $affectedLineIds = [];

        $removeVat = false;
        // Valid VAT ID and Address Matches then do not apply this tax
        if ($taxRate->isVat && ($address && $address->businessTaxId && $address->country))
        {
            $vatValidation = new Validation(['debug' => false]);

            $validBusinessTaxIdData = \Craft\craft()->cache->get('commerce:validVatId:'.$address->businessTaxId);
            if ($validBusinessTaxIdData || $vatValidation->checkNumber($address->businessTaxId))
            {
                // A valid vat ID from API was found, cache result.
                if (!$validBusinessTaxIdData)
                {
                    $validBusinessTaxIdData = $vatValidation->getData();
                    \Craft\craft()->cache->set('commerce:validVatId:'.$address->businessTaxId, $validBusinessTaxIdData);
                }

                if (isset($validBusinessTaxIdData['country']) && $validBusinessTaxIdData['country'] == $address->country->iso)
                {
                    $removeVat = true;
                }
            }
        }

        //checking addresses
        if (!$this->matchAddress($address, $zone) || $removeVat) {
            if ($taxRate->include) {
                //excluding taxes included in price
                $allRemovedTax = 0;
                foreach ($lineItems as $item) {
                    if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                        $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                        $amount = -($taxableAmount - ($taxableAmount / (1 + $taxRate->rate)));
                        $amount = CommerceCurrencyHelper::round($amount);
                        $allRemovedTax += $amount;
                        $item->tax += $amount;
                        $affectedLineIds[] = $item->id;
                    }
                }

                // We need to display the adjustment that removed the included tax
                $adjustment->name = $taxRate->name . " ". \Craft\Craft::t('Removed');
                $adjustment->amount = $allRemovedTax;
                $adjustment->optionsJson = array_merge(['lineItemsAffected'=>$affectedLineIds],$adjustment->optionsJson);
                return $adjustment;
            }

            return false;
        }

        //checking items tax categories
        $itemsMatch = false;

        foreach ($lineItems as $item)
        {

            if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                if (!$taxRate->include) {
                    $amount = $taxRate->rate * $taxableAmount;
                    $itemTax = CommerceCurrencyHelper::round($amount);
                } else {
                    $amount = $taxableAmount - ($taxableAmount / (1 + $taxRate->rate));
                    $itemTax = CommerceCurrencyHelper::round($amount);
                }

                $adjustment->amount += $itemTax;

                if (!$taxRate->include) {
                    $item->tax += $itemTax;
                }else{
                    $adjustment->included = true;
                    $item->taxIncluded += $itemTax;
                }

                $affectedLineIds[] = $item->id;
                $itemsMatch = true;
            }
        }

        $adjustment->optionsJson = array_merge(['lineItemsAffected'=>$affectedLineIds],$adjustment->optionsJson);
        return $itemsMatch ? $adjustment : false;
    }

    /**
     * @param Commerce_AddressModel $address
     * @param Commerce_TaxZoneModel $zone
     *
     * @return bool
     */
    private function matchAddress(Commerce_AddressModel $address = null, Commerce_TaxZoneModel $zone)
    {
        //when having no address check default tax zones only
        if (!$address) {
            return $zone->default;
        }

        if ($zone->countryBased) {
            $countryIds = $zone->getCountryIds();

            if (in_array($address->countryId, $countryIds)) {
                return true;
            }
        } else {

            $states = [];
            $countries = [];
            foreach ($zone->states as $state)
            {
                $states[] = $state->id;
                $countries[] = $state->countryId;
            }

            $countryAndStateMatch = (bool) (in_array($address->countryId, $countries) && in_array($address->stateId, $states));
            $countryAndStateNameMatch = (bool) (in_array($address->countryId, $countries) && strcasecmp($state->name, $address->getStateText()) == 0);
            $countryAndStateAbbrMatch = (bool) (in_array($address->countryId, $countries) && strcasecmp($state->abbreviation, $address->getStateText()) == 0);

            if ($countryAndStateMatch || $countryAndStateNameMatch || $countryAndStateAbbrMatch)
            {
                return true;
            }

        }

        return false;
    }
}
