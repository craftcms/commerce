<?php

namespace craft\commerce\adjusters;

use Craft;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\TaxRate;
use craft\commerce\models\TaxZone;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use Snowcap\Vat\Validation;

/**
 * Tax Adjustments
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Tax implements AdjusterInterface
{
    // Constants
    // =========================================================================

    const ADJUSTMENT_TYPE = 'tax';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_vatValidator;

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var Address
     */
    private $_address;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

        $this->_address = $this->_order->shippingAddressId ? Plugin::getInstance()->getAddresses()->getAddressById($this->_order->shippingAddressId) : null;

        if (Plugin::getInstance()->getSettings()->useBillingAddressForTax) {
            $this->_address = $this->_order->billingAddressId ? Plugin::getInstance()->getAddresses()->getAddressById($this->_order->billingAddressId) : null;
        }

        $adjustments = [];
        $taxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRates();

        /** @var TaxRate $rate */
        foreach ($taxRates as $rate) {
            // Apply all rates that match
            if ($newAdjustments = $this->_getAdjustments($rate)) {
                $adjustments = array_merge($adjustments, $newAdjustments);
            }
        }

        return $adjustments;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param TaxRate $taxRate
     *
     * @return OrderAdjustment[]|false
     */
    private function _getAdjustments(TaxRate $taxRate)
    {
        $zone = $taxRate->taxZone;
        $adjustments = [];

        $removeVat = false;
        // Valid VAT ID and Address Matches then do not apply this tax
        if ($taxRate->isVat && ($this->_address && $this->_address->businessTaxId && $this->_address->country) && $this->matchAddress($zone)) {
            $validBusinessTaxIdData = Craft::$app->getCache()->get('commerce:validVatId:'.$this->_address->businessTaxId);
            if ($validBusinessTaxIdData || ($this->_address->businessTaxId && $this->validateVatNumber($this->_address->businessTaxId))) {
                // A valid vat ID from API was found, cache result.
                if (!$validBusinessTaxIdData) {
                    $validBusinessTaxIdData = $this->getVatValidator()->getData();
                    Craft::$app->getCache()->set('commerce:validVatId:'.$this->_address->businessTaxId, $validBusinessTaxIdData);
                }

                if (isset($validBusinessTaxIdData['country']) && $validBusinessTaxIdData['country'] == $this->_address->country->iso) {
                    $removeVat = true;
                } else {
                    // delete validated vat ID in cache if the address country no longer matches.
                    Craft::$app->getCache()->delete('commerce:validVatId:'.$this->_address->businessTaxId);
                }
            }
        }

        //Address doesn't match zone or we should remove the VAT
        if (!$this->matchAddress($zone) || $removeVat) {
            // Since the address doesn't match or it's a removable vat tax,
            // before we return false (no taxes) remove the tax if it was included in the taxable amount.
            if ($taxRate->include) {
                // Is this an order level tax rate?
                if (in_array($taxRate->taxable, [TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE, TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING], false)) {
                    $orderTaxableAmount = 0;

                    if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                        $orderTaxableAmount = $orderTaxableAmount = $this->_order->getTotalTaxablePrice();
                    } else if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                        $orderTaxableAmount = $this->_order->totalShippingCost;
                    }

                    $amount = -($orderTaxableAmount - ($orderTaxableAmount / (1 + $taxRate->rate)));
                    $amount = Currency::round($amount);

                    $adjustment = $this->_createAdjustment($taxRate);
                    // We need to display the adjustment that removed the included tax
                    $adjustment->name = $taxRate->name.' '.Craft::t('commerce', 'Removed');
                    $adjustment->amount = $amount;
                    $adjustment->type = 'discount';

                    $adjustments[] = $adjustment;
                }

                // Not an order level taxable, modify the line items.
                foreach ($this->_order->getLineItems() as $item) {
                    if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                        $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                        $amount = -($taxableAmount - ($taxableAmount / (1 + $taxRate->rate)));
                        $amount = Currency::round($amount);

                        $adjustment = $this->_createAdjustment($taxRate);
                        // We need to display the adjustment that removed the included tax
                        $adjustment->name = $taxRate->name.' '.Craft::t('commerce', 'Removed');
                        $adjustment->amount = $amount;
                        $adjustment->lineItemId = $item->id;
                        $adjustment->type = 'discount';

                        $adjustments[] = $adjustment;
                    }
                }

                // Return the removed included taxes as discounts.
                return $adjustments;
            }

            return false;
        }

        // Is this an order level tax rate?
        if (in_array($taxRate->taxable, [TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE, TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING], false)) {
            $orderTaxableAmount = 0;

            if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                $orderTaxableAmount = $this->_order->getTotalTaxablePrice();
            }

            if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                $orderTaxableAmount = $this->_order->totalShippingCost;
            }

            if (!$taxRate->include) {
                $amount = $taxRate->rate * $orderTaxableAmount;
                $orderTax = Currency::round($amount);
            } else {
                $amount = $orderTaxableAmount - ($orderTaxableAmount / (1 + $taxRate->rate));
                $orderTax = Currency::round($amount);
            }

            $adjustment = $this->_createAdjustment($taxRate);
            // We need to display the adjustment that removed the included tax
            $adjustment->amount = $orderTax;

            if ($taxRate->include) {
                $adjustment->included = true;
            }

            return [$adjustment];
        }

        // not an order level tax rate, modify line items.
        foreach ($this->_order->getLineItems() as $item) {
            if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                if (!$taxRate->include) {
                    $amount = $taxRate->rate * $taxableAmount;
                    $itemTax = Currency::round($amount);
                } else {
                    $amount = $taxableAmount - ($taxableAmount / (1 + $taxRate->rate));
                    $itemTax = Currency::round($amount);
                }

                $adjustment = $this->_createAdjustment($taxRate);
                // We need to display the adjustment that removed the included tax
                $adjustment->amount = $itemTax;
                $adjustment->lineItemId = $item->id;

                if ($taxRate->include) {
                    $adjustment->included = true;
                }

                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @param TaxZone $zone
     *
     * @return bool
     */
    private function matchAddress(TaxZone $zone): bool
    {
        //when having no address check default tax zones only
        if (!$this->_address) {
            return $zone->default;
        }

        if ($zone->countryBased) {
            $countryIds = $zone->getCountryIds();

            if (in_array($this->_address->countryId, $countryIds, false)) {
                return true;
            }
        } else {
            $states = [];
            $countries = [];
            foreach ($zone->states as $state) {
                $states[] = $state->id;
                $countries[] = $state->countryId;
            }

            $countryAndStateMatch = (in_array($this->_address->countryId, $countries, false) && in_array($this->_address->stateId, $states, false));
            $countryAndStateNameMatch = (in_array($this->_address->countryId, $countries, false) && strcasecmp($this->_address->state->name ?? '', $this->_address->getStateText()) == 0);
            $countryAndStateAbbrMatch = (in_array($this->_address->countryId, $countries, false) && strcasecmp($this->_address->state->abbreviation ?? '', $this->_address->getStateText()) == 0);

            if ($countryAndStateMatch || $countryAndStateNameMatch || $countryAndStateAbbrMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $businessVatId
     *
     * @return bool
     */
    private function validateVatNumber(int $businessVatId)
    {
        try {
            return $this->getVatValidator()->checkNumber($businessVatId);
        } catch (\Exception $e) {
            Craft::error('Communication with VAT API failed: '.$e->getMessage(), __METHOD__);

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

    /**
     * @param $rate
     *
     * @return OrderAdjustment
     */
    private function _createAdjustment($rate): OrderAdjustment
    {
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $rate->name;
        $adjustment->description = $rate->rate * 100 .'%'.($rate->include ? ' inc' : '');
        $adjustment->orderId = $this->_order->id;
        $adjustment->sourceSnapshot = $rate->attributes;

        return $adjustment;
    }
}
