<?php

namespace craft\commerce\adjusters;

use Craft;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\TaxRate;
use craft\commerce\models\TaxZone;
use craft\commerce\Plugin;
use Snowcap\Vat\Validation;

/**
 * Tax Adjustments
 *
 * @package Commerce\Adjusters
 */
class Tax implements AdjusterInterface
{
    const ADJUSTMENT_TYPE = 'Tax';

    private $_vatValidator;

    /**
     * @param Order      $order
     * @param LineItem[] $lineItems
     *
     * @return \craft\commerce\models\OrderAdjustment[]
     */
    public function adjust(Order &$order, array $lineItems = [])
    {
        $address = Plugin::getInstance()->getAddresses()->getAddressById($order->shippingAddressId);

        if (Plugin::getInstance()->getSettings()->getSettings()->useBillingAddressForTax) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($order->billingAddressId);
        }

        $adjustments = [];
        $taxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRatesWithCountries();

        /** @var TaxRate $rate */
        foreach ($taxRates as $rate) {
            if ($adjustment = $this->getAdjustment($order, $lineItems, $address, $rate)) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @param Order      $order
     * @param LineItem[] $lineItems
     * @param Address    $address
     * @param TaxRate    $taxRate
     *
     * @return OrderAdjustment|false
     */
    private function getAdjustment(Order $order, array $lineItems, Address $address = null, TaxRate $taxRate)
    {
        $zone = $taxRate->taxZone;

        //preparing model
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $taxRate->name;
        $adjustment->description = $taxRate->rate * 100 .'%'.($taxRate->include ? ' inc' : '');
        $adjustment->orderId = $order->id;
        $adjustment->optionsJson = $taxRate->attributes;

        $affectedLineIds = [];

        $removeVat = false;
        // Valid VAT ID and Address Matches then do not apply this tax
        if ($taxRate->isVat && ($address && $address->businessTaxId && $address->country) && $this->matchAddress($address, $zone)) {
            $validBusinessTaxIdData = Craft::$app->getCache()->get('commerce:validVatId:'.$address->businessTaxId);
            if ($validBusinessTaxIdData || $this->validateVatNumber($address->businessTaxId)) {
                // A valid vat ID from API was found, cache result.
                if (!$validBusinessTaxIdData) {
                    $validBusinessTaxIdData = $this->getVatValidator()->getData();
                    Craft::$app->getCache()->set('commerce:validVatId:'.$address->businessTaxId, $validBusinessTaxIdData);
                }

                if (isset($validBusinessTaxIdData['country']) && $validBusinessTaxIdData['country'] == $address->country->iso) {
                    $removeVat = true;
                } else {
                    // delete validated vat ID in cache if the address country no longer matches.
                    Craft::$app->getCache()->delete('commerce:validVatId:'.$address->businessTaxId);
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
                        $amount = Currency::round($amount);
                        $allRemovedTax += $amount;
                        $item->tax += $amount;
                        $affectedLineIds[] = $item->id;
                    }
                }

                // We need to display the adjustment that removed the included tax
                $adjustment->name = $taxRate->name." ".Craft::t('commerce', 'commerce', 'Removed');
                $adjustment->amount = $allRemovedTax;
                $adjustment->optionsJson = array_merge(['lineItemsAffected' => $affectedLineIds], $adjustment->optionsJson);

                return $adjustment;
            }

            return false;
        }

        //checking items tax categories
        $itemsMatch = false;

        foreach ($lineItems as $item) {

            if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                if (!$taxRate->include) {
                    $amount = $taxRate->rate * $taxableAmount;
                    $itemTax = Currency::round($amount);
                } else {
                    $amount = $taxableAmount - ($taxableAmount / (1 + $taxRate->rate));
                    $itemTax = Currency::round($amount);
                }

                $adjustment->amount += $itemTax;

                if (!$taxRate->include) {
                    $item->tax += $itemTax;
                } else {
                    $adjustment->included = true;
                    $item->taxIncluded += $itemTax;
                }

                $affectedLineIds[] = $item->id;
                $itemsMatch = true;
            }
        }

        $adjustment->optionsJson = array_merge(['lineItemsAffected' => $affectedLineIds], $adjustment->optionsJson);

        return $itemsMatch ? $adjustment : false;
    }

    /**
     * @param Address $address
     * @param TaxZone $zone
     *
     * @return bool
     */
    private function matchAddress(Address $address = null, TaxZone $zone)
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
            foreach ($zone->states as $state) {
                $states[] = $state->id;
                $countries[] = $state->countryId;
            }

            $countryAndStateMatch = (bool)(in_array($address->countryId, $countries) && in_array($address->stateId, $states));
            $countryAndStateNameMatch = (bool)(in_array($address->countryId, $countries) && strcasecmp($address->state->name ?? '', $address->getStateText()) == 0);
            $countryAndStateAbbrMatch = (bool)(in_array($address->countryId, $countries) && strcasecmp($address->state->abbreviation ?? '', $address->getStateText()) == 0);

            if ($countryAndStateMatch || $countryAndStateNameMatch || $countryAndStateAbbrMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $businessVatId
     *
     * @return bool
     */
    private function validateVatNumber($businessVatId)
    {
        try {
            return $this->getVatValidator()->checkNumber($businessVatId);
        } catch (\Exception $e) {
            Craft::error("Communication with VAT API failed: ".$e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * @return Validation
     */
    private function getVatValidator()
    {
        if ($this->_vatValidator === null) {
            $this->_vatValidator = new Validation(['debug' => false]);
        }

        return $this->_vatValidator;
    }
}
